<?php

namespace CharlesStOlive\FilamentMap\Services;

use CharlesStOlive\FilamentMap\Models\GeoPoint;
use CharlesStOlive\FilamentMap\Models\MapLayer;
use CharlesStOlive\FilamentMap\Models\MapScene;
use CharlesStOlive\FilamentMap\Support\MapKeys;
use CharlesStOlive\FilamentMap\Support\MarkerShapes;
use CharlesStOlive\FilamentMap\Support\MarkerSvg;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MapPayloadBuilder
{
    public function build(MapScene $scene, array $overrides = []): ?array
    {
        if (! $scene->is_active) {
            return null;
        }

        if (array_key_exists('layers', $overrides)) {
            throw ValidationException::withMessages(['layers' => 'Les couches se configurent dans la scène cartographique.']);
        }

        $scene->loadMissing(['layers']);

        $payload = [
            'map' => $this->scene($scene),
            'layers' => $scene->layers
                ->where('is_active', true)
                ->values()
                ->map(fn (MapLayer $layer): array => $this->layer($layer))
                ->all(),
            'points' => [],
            'scene' => ['id' => $scene->getKey(), 'key' => $scene->slug],
            'clustering' => $this->clustering($scene),
            'controls' => [
                'zoom' => true,
                'layers' => true,
            ],
        ];

        $collectionOverrides = Arr::only($overrides, ['layers', 'points']);
        $payload = array_replace_recursive($payload, Arr::except($overrides, ['layers', 'points']));

        foreach ($collectionOverrides as $key => $value) {
            $payload[$key] = array_values($value ?? []);
        }

        return $payload;
    }

    protected function scene(MapScene $scene): array
    {
        // Le cast `decimal:2` du modèle renvoie des chaînes ("18.00") : il faut
        // des nombres dans le payload, sinon le JS les compare comme du texte
        // ("18.00" < "2.00") et MapLibre refuse minZoom > maxZoom.
        $minZoom = (float) ($scene->min_zoom ?? config('filament-map.default.min_zoom'));
        $maxZoom = (float) ($scene->max_zoom ?? config('filament-map.default.max_zoom'));
        $zoom = (float) ($scene->zoom ?? config('filament-map.default.zoom'));

        return [
            'id' => $scene->getKey(),
            'key' => $scene->slug,
            'name' => $scene->name,
            'mode' => $scene->mode,
            'center' => [
                'lat' => $scene->center_latitude !== null ? (float) $scene->center_latitude : (float) config('filament-map.default.lat'),
                'lng' => $scene->center_longitude !== null ? (float) $scene->center_longitude : (float) config('filament-map.default.lng'),
            ],
            'zoom' => max($minZoom, min($maxZoom, $zoom)),
            'minZoom' => $minZoom,
            'maxZoom' => $maxZoom,
            'bounds' => $scene->bounds,
            'options' => $scene->options ?? [],
        ];
    }

    public function layer(MapLayer $layer): array
    {
        $pivot = $layer->pivot;

        return [
            'id' => $layer->getKey(),
            'name' => $layer->name,
            'key' => $layer->key,
            'type' => $layer->renderType(),
            'visible' => (bool) ($pivot?->is_visible_by_default ?? true),
            'source' => $this->resolveKeys($this->layerSource($layer)),
            'style' => array_replace_recursive($layer->style ?? [], $this->pivotJson($pivot?->style)),
            'styleRules' => array_replace_recursive($layer->style_rules ?? [], $this->pivotJson($pivot?->style_rules)),
            'options' => $this->resolveKeys(array_replace_recursive($layer->options ?? [], $this->pivotJson($pivot?->options))),
            'sortOrder' => $pivot?->sort_order ?? 0,
        ];
    }

    /**
     * Remplace « {key:maptiler} » par la clé du fournisseur dans toutes les chaînes de la couche (voir Support\MapKeys).
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    protected function resolveKeys(array $value): array
    {
        return MapKeys::resolve($value);
    }

    protected function layerSource(MapLayer $layer): array
    {
        $media = $layer->getFirstMedia(config('filament-map.media_collections.layer_source', 'layer_source'));

        if ($media !== null) {
            return ['type' => 'url', 'url' => $media->getUrl()];
        }

        if ($layer->isBaseMap()) {
            return ['type' => 'url', 'url' => $layer->baseMapUrl()];
        }

        return match ($layer->source_type) {
            'url' => ['type' => 'url', 'url' => $layer->source_url],
            'file', 'path' => ['type' => 'url', 'url' => $this->storedFileUrl($layer->source_path)],
            'json' => ['type' => 'json', 'data' => $layer->source_json],
            default => ['type' => null],
        };
    }

    protected function storedFileUrl(mixed $path): ?string
    {
        $path = $this->normalizeStoredPath($path);

        if (! filled($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk(config('filament-map.files.disk', 'public'))->url($path);
    }

    protected function normalizeStoredPath(mixed $path): ?string
    {
        if (is_string($path)) {
            return $path;
        }

        if (is_array($path)) {
            foreach ($path as $value) {
                $normalized = $this->normalizeStoredPath($value);

                if (filled($normalized)) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    /**
     * @param  string|null  $image  Une image que l'appelant propose au point (une application, un parcours : l'image
     *                              de une d'une étape, par exemple). Elle passe après l'image propre au point et avant
     *                              celle de son type, et ne sert que si le contenu du marqueur est « Image » : ailleurs,
     *                              elle est ignorée.
     */
    public function point(GeoPoint $point, ?string $image = null): array
    {
        $pivot = $point->pivot;
        $type = $point->type;
        $markerStyle = array_replace_recursive(
            $type?->marker_style ?? [],
            $point->marker_style ?? [],
            $this->pivotJson($pivot?->marker_style),
        );
        $options = array_replace_recursive($point->options ?? [], $this->pivotJson($pivot?->options));
        $image = $this->markerImage($point, $image);
        $icon = $options['icon'] ?? $type?->icon;
        $color = $options['color'] ?? $type?->color;

        return [
            'id' => $point->getKey(),
            'key' => $point->slug,
            'type' => $type?->key,
            'name' => $pivot?->label ?: $point->name,
            'description' => $point->description,
            'position' => [
                'lat' => (float) $point->latitude,
                'lng' => (float) $point->longitude,
            ],
            'visible' => (bool) ($pivot?->is_visible_by_default ?? true),
            'layerId' => $pivot?->layer_id,
            'tooltip' => $pivot?->tooltip,
            'popup' => $pivot?->popup_content,
            'icon' => $icon,
            'color' => $color,
            'image' => $image,
            'style' => $markerStyle,
            'appearance' => $this->appearance($markerStyle, $icon, $image, $color),
            'cluster' => [
                'enabled' => (bool) ($options['clusterable'] ?? true),
                'group' => $options['cluster_group'] ?? 'default',
            ],
            'options' => $options,
            'sortOrder' => $pivot?->sort_order ?? 0,
        ];
    }

    /**
     * Ce que le navigateur dessine (resources/js/layers/marker-layer.js), tout prêt : la forme (MarkerShapes : son SVG
     * nettoyé, sa zone de contenu `slot` en %, son ancrage, sa taille en px), le contenu de la zone (l'icône déjà en
     * SVG dans `content.html`), la couleur et les variables de style. L'aperçu d'un type (MarkerPreview) passe par ici.
     *
     * Ce qui manque se rabat sans erreur : un contenu « Image » sans image montre l'icône, une icône inconnue rien, une
     * forme sans zone de contenu rien d'autre qu'elle-même, un SVG illisible l'épingle.
     *
     * @param  array<string, mixed>  $style  Le `marker_style` du type, complété par celui du point.
     */
    public function appearance(array $style, ?string $icon = null, ?string $image = null, ?string $color = null): array
    {
        $shape = MarkerShapes::resolve($style);
        $content = is_array($style['content'] ?? null) ? $style['content'] : [];
        $declared = $content['type'] ?? config('filament-map.markers.content_type', 'icon');
        $contentType = $declared === 'image' && blank($image) ? 'icon' : $declared;
        $contentValue = match ($contentType) {
            'image' => $image,
            // La valeur saisie est le nom de l'icône quand le contenu est « Icône » ; sinon, celle du point ou du type.
            'icon' => $declared === 'icon' && filled($content['value'] ?? null) ? $content['value'] : $icon,
            'text' => $content['value'] ?? null,
            default => null,
        };
        $html = $contentType === 'icon' ? MarkerSvg::icon($contentValue) : null;

        if ($shape['slot'] === null || blank($contentValue) || ($contentType === 'icon' && $html === null)) {
            [$contentType, $contentValue, $html] = ['none', null, null];
        }

        return [
            'shape' => $shape['name'],
            'svg' => $shape['svg'],
            'slot' => $shape['slot'],
            'anchor' => $shape['anchor'],
            'content' => ['type' => $contentType, 'value' => $contentValue, 'html' => $html],
            'color' => $color,
            'size' => ['width' => $shape['width'], 'height' => $shape['height']],
            'css' => is_array($style['css'] ?? null) ? $style['css'] : [],
        ];
    }

    protected function clustering(MapScene $scene): array
    {
        $sceneClustering = Arr::get($scene->options ?? [], 'clustering', []);

        return array_replace_recursive(
            config('filament-map.clustering', []),
            is_array($sceneClustering) ? $sceneClustering : [],
        );
    }

    protected function pivotJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && filled($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /** L'image du marqueur : celle du point, sinon celle que l'appelant propose, sinon celle de son type. */
    protected function markerImage(GeoPoint $point, ?string $proposed = null): ?string
    {
        $collection = config('filament-map.media_collections.marker_image', 'marker_image');
        $typeCollection = config('filament-map.media_collections.default_marker_image', 'default_marker_image');

        return $point->getFirstMediaUrl($collection) ?: $proposed ?: $point->type?->getFirstMediaUrl($typeCollection) ?: null;
    }
}
