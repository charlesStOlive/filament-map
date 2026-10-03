<?php

namespace CharlesStOlive\FilamentMap\Services;

use CharlesStOlive\FilamentMap\Models\MapLayer;
use CharlesStOlive\FilamentMap\Support\MapKeys;
use CharlesStOlive\FilamentMap\Support\MapLayerCheck;
use CharlesStOlive\FilamentMap\Support\UnsafeMapSourceUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Vérifie qu'une couche s'affichera : sa source répond, et elle contient ce que son type attend (un style MapLibre, des
 * tuiles images, du GeoJSON, un SVG). La vérification part du serveur : elle ne voit pas les restrictions propres au
 * navigateur, sinon l'en-tête CORS, signalé en réserve.
 */
class MapLayerChecker
{
    protected const GEOJSON_TYPES = [
        'FeatureCollection', 'Feature', 'GeometryCollection', 'Point', 'MultiPoint', 'LineString', 'MultiLineString',
        'Polygon', 'MultiPolygon',
    ];

    /** Vérifie la couche et garde le résultat sur elle, sans toucher à sa date de modification. */
    public function checkAndStore(MapLayer $layer): MapLayerCheck
    {
        $check = $this->check($layer);
        $values = ['check_status' => $check->status, 'check_message' => $check->message, 'checked_at' => now()];

        $layer->newQuery()->toBase()->where($layer->getKeyName(), $layer->getKey())->update($values);
        $layer->forceFill($values)->syncOriginalAttributes(array_keys($values));

        return $check;
    }

    public function check(MapLayer $layer): MapLayerCheck
    {
        $check = $this->runCheck($layer);

        // Un message peut citer l'URL appelée : la clé n'y reste pas en clair.
        return new MapLayerCheck($check->status, MapKeys::redact($check->message));
    }

    protected function runCheck(MapLayer $layer): MapLayerCheck
    {
        try {
            return match ($layer->renderType()) {
                'style' => $this->checkStyle($layer),
                'tile' => $this->checkTiles($layer),
                'geojson', 'points' => $this->checkGeoJson($layer),
                'svg_overlay' => $this->checkSvg($layer),
                'custom' => MapLayerCheck::warning('Couche personnalisée : son rendu dépend du projet, elle n’est pas vérifiée automatiquement.'),
                default => MapLayerCheck::error('Type de couche inconnu : « '.$layer->type.' ».'),
            };
        } catch (UnsafeMapSourceUrl $exception) {
            return MapLayerCheck::error($exception->getMessage());
        } catch (ConnectionException $exception) {
            return MapLayerCheck::error('Adresse injoignable : '.Str::limit($exception->getMessage(), 160));
        } catch (Throwable $exception) {
            return MapLayerCheck::error('Vérification impossible : '.Str::limit($exception->getMessage(), 160));
        }
    }

    protected function checkStyle(MapLayer $layer): MapLayerCheck
    {
        $url = $layer->baseMapUrl();

        if (! filled($url)) {
            return MapLayerCheck::error('Aucune adresse : renseigne l’URL du style, qui finit en général par style.json.');
        }

        if ($missing = $this->missingKeys($url)) {
            return $missing;
        }

        $response = $this->fetch(MapKeys::resolve($url));

        if (! $response->successful()) {
            return $this->httpError($response);
        }

        $style = $response->json();

        if (! is_array($style) || ($style['version'] ?? null) !== 8 || ! is_array($style['layers'] ?? null)) {
            return $this->isImage($response)
                ? MapLayerCheck::error('Cette adresse renvoie une image, pas un style : choisis le type « Fond de carte en tuiles images ».')
                : MapLayerCheck::error('La réponse n’est pas un style MapLibre (style.json, version 8).');
        }

        $name = filled($style['name'] ?? null) ? ' « '.$style['name'].' »' : '';
        $sources = count((array) ($style['sources'] ?? []));
        $layers = count($style['layers']);

        return MapLayerCheck::ok("Style{$name} chargé : {$sources} source(s), {$layers} calque(s).")
            ->withWarning($this->corsWarning($response));
    }

    protected function checkTiles(MapLayer $layer): MapLayerCheck
    {
        $url = $layer->baseMapUrl();

        if (! filled($url)) {
            return MapLayerCheck::error('Aucune adresse : renseigne l’URL des tuiles, avec {z}, {x} et {y}.');
        }

        if ($missing = $this->missingKeys($url)) {
            return $missing;
        }

        $resolved = MapKeys::resolve($url);

        if (! (str_contains($resolved, '{z}') && str_contains($resolved, '{x}') && str_contains($resolved, '{y}'))
            && ! str_contains($resolved, '{quadkey}') && ! str_contains($resolved, '{bbox-epsg-3857}')) {
            $response = $this->fetch($resolved);
            $json = $response->successful() ? $response->json() : null;

            return is_array($json) && ($json['version'] ?? null) === 8
                ? MapLayerCheck::error('Cette adresse est un style (style.json), pas des tuiles : choisis le type « Fond de carte vectoriel ».')
                : MapLayerCheck::error('L’URL des tuiles doit contenir {z}, {x} et {y}, remplacés à l’affichage par le zoom et la position.');
        }

        if (str_contains($resolved, '{s}')) {
            return MapLayerCheck::error('MapLibre ne remplace pas {s} : écris un sous-domaine à la place (a, b ou c), ou retire-le.');
        }

        $sample = strtr($resolved, ['{z}' => '0', '{x}' => '0', '{y}' => '0', '{r}' => '', '{quadkey}' => '0', '{bbox-epsg-3857}' => '-20037508.34,-20037508.34,20037508.34,20037508.34']);
        $response = $this->fetch($sample);

        if (! $response->successful()) {
            return $this->httpError($response, 'La tuile d’essai (zoom 0)');
        }

        $type = Str::before((string) $response->header('Content-Type'), ';');

        if (! $this->isImage($response) && ! Str::contains($type, ['protobuf', 'mvt', 'octet-stream'])) {
            return MapLayerCheck::error('La tuile d’essai (zoom 0) n’est pas une image ('.($type ?: 'type inconnu').').');
        }

        return MapLayerCheck::ok('Tuile d’essai reçue ('.$type.').')->withWarning($this->corsWarning($response));
    }

    protected function checkGeoJson(MapLayer $layer): MapLayerCheck
    {
        [$content, $response, $problem] = $this->sourceContent($layer);

        if ($problem) {
            return $problem;
        }

        $data = is_array($content) ? $content : json_decode((string) $content, true);

        if (! is_array($data)) {
            return MapLayerCheck::error('La source n’est pas du JSON lisible'.(is_string($content) ? ' ('.json_last_error_msg().')' : '').'.');
        }

        if (! in_array($data['type'] ?? null, self::GEOJSON_TYPES, true)) {
            return MapLayerCheck::error('Le JSON n’est pas du GeoJSON : il lui manque un « type » comme FeatureCollection ou Feature.');
        }

        $count = match ($data['type']) {
            'FeatureCollection' => count((array) ($data['features'] ?? [])),
            'GeometryCollection' => count((array) ($data['geometries'] ?? [])),
            default => 1,
        };

        $check = $count === 0
            ? MapLayerCheck::warning('GeoJSON valide mais vide : aucune entité à afficher.')
            : MapLayerCheck::ok("GeoJSON lu : {$count} entité(s).");

        return $check->withWarning($response ? $this->corsWarning($response) : null);
    }

    protected function checkSvg(MapLayer $layer): MapLayerCheck
    {
        $bounds = ($layer->options ?? [])['bounds'] ?? null;

        if (! isset($bounds['southWest']['lat'], $bounds['southWest']['lng'], $bounds['northEast']['lat'], $bounds['northEast']['lng'])) {
            return MapLayerCheck::error('L’emprise manque : les options doivent préciser bounds.southWest et bounds.northEast (lat, lng).');
        }

        [$content, $response, $problem] = $this->sourceContent($layer);

        if ($problem) {
            return $problem;
        }

        if (! is_string($content) || ! str_contains($content, '<svg')) {
            return MapLayerCheck::error('La source n’est pas un fichier SVG.');
        }

        return MapLayerCheck::ok('Image SVG lue, emprise renseignée.')
            ->withWarning($response ? $this->corsWarning($response) : null);
    }

    /**
     * Le contenu de la source d'une couche de données, d'où que vienne la source, et la réponse HTTP s'il a fallu la
     * demander à un autre serveur.
     *
     * @return array{0: mixed, 1: ?Response, 2: ?MapLayerCheck}
     */
    protected function sourceContent(MapLayer $layer): array
    {
        $media = $layer->getFirstMedia(config('filament-map.media_collections.layer_source', 'layer_source'));

        if ($media !== null) {
            return is_file($media->getPath())
                ? [file_get_contents($media->getPath()), null, null]
                : [null, null, MapLayerCheck::error('Le fichier joint à la couche est introuvable sur le disque.')];
        }

        if ($layer->source_type === 'json') {
            return filled($layer->source_json)
                ? [$layer->source_json, null, null]
                : [null, null, MapLayerCheck::error('Aucune donnée : colle le GeoJSON dans « Source JSON ».')];
        }

        if (in_array($layer->source_type, ['file', 'path'], true)) {
            $path = is_array($layer->source_path) ? collect($layer->source_path)->flatten()->first() : $layer->source_path;
            $disk = Storage::disk(config('filament-map.files.disk', 'public'));

            if (! filled($path)) {
                return [null, null, MapLayerCheck::error('Aucun fichier : téléverse le fichier source puis enregistre.')];
            }

            return $disk->exists($path)
                ? [$disk->get($path), null, null]
                : [null, null, MapLayerCheck::error("Le fichier « {$path} » est introuvable sur le disque.")];
        }

        $url = $layer->source_url;

        if (! filled($url)) {
            return [null, null, MapLayerCheck::error('Aucune adresse : renseigne l’URL de la source.')];
        }

        if ($missing = $this->missingKeys($url)) {
            return [null, null, $missing];
        }

        $url = MapKeys::resolve($url);

        if ($local = $this->localPublicFile($url)) {
            if (! $this->isInsidePublic($local)) {
                return [null, null, MapLayerCheck::error('Adresse refusée : elle sort du dossier public de l’application.')];
            }

            return is_file($local)
                ? [file_get_contents($local), null, null]
                : [null, null, MapLayerCheck::error("Le fichier « {$url} » est introuvable dans le dossier public.")];
        }

        $response = $this->fetch($url);

        return $response->successful()
            ? [$response->body(), $response, null]
            : [null, null, $this->httpError($response)];
    }

    /** Une URL de l'application (« /storage/… » ou l'URL de l'app) se lit sur le disque : le serveur ne s'appelle pas lui-même. */
    protected function localPublicFile(string $url): ?string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $path = match (true) {
            str_starts_with($url, '/') && ! str_starts_with($url, '//') => $url,
            filled($appUrl) && str_starts_with($url, $appUrl.'/') => Str::after($url, $appUrl),
            default => null,
        };

        return $path === null ? null : public_path(ltrim(Str::before($path, '?'), '/'));
    }

    protected function isInsidePublic(string $path): bool
    {
        $public = realpath(public_path());
        $real = realpath($path);

        // Un fichier absent ne se lit pas : seul compte qu'un chemin existant reste dans public/.
        return $real === false
            ? ! str_contains($path, '..')
            : $public !== false && str_starts_with($real, $public.DIRECTORY_SEPARATOR);
    }

    protected function missingKeys(string $url): ?MapLayerCheck
    {
        $missing = MapKeys::missing($url);

        if ($missing === []) {
            return null;
        }

        $name = $missing[0];

        return MapLayerCheck::error("La clé « {$name} » citée par {key:{$name}} n’est pas configurée : renseigne filament-map.keys.{$name} (en général une variable du fichier .env, comme ".strtoupper($name).'_API_KEY).');
    }

    protected function fetch(string $url): Response
    {
        $this->guardUrl($url);

        return Http::timeout(10)
            ->withOptions(['allow_redirects' => [
                'max' => 3,
                'protocols' => ['http', 'https'],
                'on_redirect' => fn ($request, $response, $uri) => $this->guardUrl((string) $uri),
            ]])
            ->withHeaders(['Origin' => rtrim((string) config('app.url'), '/'), 'Accept' => '*/*'])
            ->get($url);
    }

    /**
     * Le serveur n'appelle que des adresses publiques en http(s) : une couche ne doit pas lui faire interroger le réseau
     * interne (base de données, services du conteneur, métadonnées d'hébergeur). Vaut aussi pour chaque redirection.
     */
    protected function guardUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = trim($parts['host'] ?? '', '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new UnsafeMapSourceUrl('Adresse refusée : seules les adresses http:// ou https:// complètes sont vérifiées.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveHost($host);

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new UnsafeMapSourceUrl('Adresse refusée : elle mène au réseau interne ('.$host.'), le serveur ne l’interroge pas.');
            }
        }
    }

    /** @return array<int, string> */
    protected function resolveHost(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return $ips;
    }

    protected function httpError(Response $response, string $subject = 'L’adresse'): MapLayerCheck
    {
        $status = $response->status();

        return MapLayerCheck::error(match (true) {
            in_array($status, [401, 403], true) => "{$subject} est refusée ({$status}) : clé absente ou invalide, clé limitée à d’autres domaines, ou carte non publique chez le fournisseur.",
            $status === 404 => "{$subject} est introuvable (404) : vérifie l’URL (identifiant de la carte, nom du fichier).",
            $status === 429 => "{$subject} est refusée pour l’instant (429) : quota du fournisseur dépassé.",
            default => "{$subject} répond par une erreur ({$status}).",
        });
    }

    protected function corsWarning(Response $response): ?string
    {
        return filled($response->header('Access-Control-Allow-Origin'))
            ? null
            : 'Réserve : le serveur n’autorise pas explicitement l’affichage depuis un autre site (en-tête CORS absent), la carte risque de rester vide dans le navigateur.';
    }

    protected function isImage(Response $response): bool
    {
        return str_starts_with((string) $response->header('Content-Type'), 'image/');
    }
}
