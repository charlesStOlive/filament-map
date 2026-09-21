<?php

namespace CharlesStOlive\FilamentMap\Services\Geocoding;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Nominatim, le service de recherche d'OpenStreetMap (https://nominatim.org).
 *
 * L'instance publique est gratuite, sans clé, mais sa politique d'usage (https://operations.osmfoundation.org/policies/nominatim/)
 * demande : une requête par seconde au plus, un User-Agent qui identifie l'application, pas de recherche à chaque frappe, et de
 * garder les réponses. Cette classe le fait : limite d'une requête par seconde pour toute l'application, User-Agent (et
 * courriel, si on en donne un), réponses gardées en cache. Elle convient à un usage occasionnel ; pour un gros volume, on
 * héberge sa propre instance (`filament-map.geocoding.url`) ou on change de service.
 *
 * Les réglages sont sous `filament-map.geocoding`.
 */
class NominatimGeocoder implements Geocoder
{
    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $limit = max(1, min(10, $limit));
        // La langue de l'application, puis l'anglais : sans nom dans la première, Nominatim retombe sur l'écriture locale
        // (du khmer, du thaï…), que l'anglais évite le plus souvent.
        $locale = (string) (config('filament-map.geocoding.language') ?: implode(',', array_unique([app()->getLocale(), 'en'])));

        $rows = $this->cached($query, $limit, $locale, fn (): array => $this->request($query, $limit, $locale));

        return array_values(array_filter(array_map($this->toResult(...), $rows)));
    }

    /**
     * @param  callable(): array<int, array<string, mixed>>  $resolve
     * @return array<int, array<string, mixed>>
     */
    private function cached(string $query, int $limit, string $locale, callable $resolve): array
    {
        $ttl = (int) config('filament-map.geocoding.cache_seconds', 60 * 60 * 24 * 30);

        if ($ttl <= 0) {
            return $resolve();
        }

        return Cache::remember(
            'filament-map:geocode:'.md5(mb_strtolower($query).'|'.$limit.'|'.$locale.'|'.config('filament-map.geocoding.country_codes')),
            $ttl,
            $resolve,
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function request(string $query, int $limit, string $locale): array
    {
        // Une requête par seconde pour toute l'application : c'est la règle de l'instance publique.
        if (! RateLimiter::attempt('filament-map-geocoding', 1, static fn (): bool => true, 1)) {
            throw new GeocodingException('Trop de recherches d’affilée : réessayez dans une seconde.');
        }

        $email = config('filament-map.geocoding.email');

        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent()])
                ->acceptJson()
                ->timeout((int) config('filament-map.geocoding.timeout', 6))
                ->get((string) config('filament-map.geocoding.url', 'https://nominatim.openstreetmap.org/search'), array_filter([
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => $limit,
                    'accept-language' => $locale,
                    'countrycodes' => config('filament-map.geocoding.country_codes'),
                    'email' => $email,
                ]))
                ->throw();
        } catch (ConnectionException) {
            throw new GeocodingException('Le service de recherche d’adresse ne répond pas. Réessayez plus tard.');
        } catch (RequestException $exception) {
            throw new GeocodingException($exception->response->status() === 429
                ? 'Trop de recherches : le service d’adresses demande de patienter.'
                : 'Le service de recherche d’adresse a refusé la recherche.');
        }

        $rows = $response->json();

        return is_array($rows) ? $rows : [];
    }

    private function userAgent(): string
    {
        return (string) (config('filament-map.geocoding.user_agent')
            ?: trim(config('app.name', 'Laravel').' ('.config('app.url', '').')'));
    }

    /** @param  array<string, mixed>  $row */
    private function toResult(array $row): ?GeocodingResult
    {
        if (! isset($row['lat'], $row['lon']) || ! is_numeric($row['lat']) || ! is_numeric($row['lon'])) {
            return null;
        }

        $box = $row['boundingbox'] ?? null;
        $bounds = is_array($box) && count($box) === 4 && count(array_filter($box, 'is_numeric')) === 4
            ? ['south' => (float) $box[0], 'north' => (float) $box[1], 'west' => (float) $box[2], 'east' => (float) $box[3]]
            : null;

        return new GeocodingResult(
            label: (string) ($row['display_name'] ?? $row['name'] ?? ''),
            latitude: (float) $row['lat'],
            longitude: (float) $row['lon'],
            bounds: $bounds,
        );
    }
}
