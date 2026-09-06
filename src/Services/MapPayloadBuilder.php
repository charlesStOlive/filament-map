<?php

namespace CharlesStOlive\FilamentMap\Services;

use CharlesStOlive\FilamentMap\Models\GeoPoint;
use CharlesStOlive\FilamentMap\Models\Map;
use CharlesStOlive\FilamentMap\Models\MapLayer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class MapPayloadBuilder
{
    public function build(Map $map, array $overrides = []): array
    {
        $map->loadMissing([
            'layers',
            'points.type',
            'points.media',
            'points.type.media',
        ]);

        $payload = [
            'map' => $this->map($map),
            'layers' => $map->layers
                ->where('is_active', true)
                ->values()
                ->map(fn (MapLayer $layer): array => $this->layer($layer))
                ->all(),
            'points' => $map->points
                ->where('is_active', true)
                ->values()
                ->map(fn (GeoPoint $point): array => $this->point($point))
                ->all(),
            'clustering' => $this->clustering($map),
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

    protected function map(Map $map): array
    {
        return [
            'id' => $map->getKey(),
            'key' => $map->slug,
            'name' => $map->name,
            'mode' => $map->mode,
            'center' => [
                'lat' => $map->center_latitude !== null ? (float) $map->center_latitude : (float) config('filament-map.default.lat'),
                'lng' => $map->center_longitude !== null ? (float) $map->center_longitude : (float) config('filament-map.default.lng'),
            ],
            'zoom' => $map->zoom ?? config('filament-map.default.zoom'),
            'minZoom' => $map->min_zoom ?? config('filament-map.default.min_zoom'),
            'maxZoom' => $map->max_zoom ?? config('filament-map.default.max_zoom'),
            'bounds' => $map->bounds,
            'options' => $map->options ?? [],
        ];
    }

    public function layer(MapLayer $layer): array
    {
        $pivot = $layer->pivot;

        return [
            'id' => $layer->getKey(),
            'name' => $layer->name,
            'key' => $layer->key,
            'type' => $layer->type,
            'visible' => (bool) ($pivot?->is_visible_by_default ?? true),
            'source' => $this->layerSource($layer),
            'style' => array_replace_recursive($layer->style ?? [], $this->pivotJson($pivot?->style)),
            'styleRules' => array_replace_recursive($layer->style_rules ?? [], $this->pivotJson($pivot?->style_rules)),
            'options' => array_replace_recursive($layer->options ?? [], $this->pivotJson($pivot?->options)),
            'sortOrder' => $pivot?->sort_order ?? 0,
        ];
    }

    protected function layerSource(MapLayer $layer): array
    {
        $media = $layer->getFirstMedia(config('filament-map.media_collections.layer_source', 'layer_source'));

        if ($media !== null) {
            return ['type' => 'url', 'url' => $media->getUrl()];
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

    public function point(GeoPoint $point): array
    {
        $pivot = $point->pivot;
        $type = $point->type;
        $markerStyle = array_replace_recursive(
            $type?->marker_style ?? [],
            $point->marker_style ?? [],
            $this->pivotJson($pivot?->marker_style),
        );
        $options = array_replace_recursive($point->options ?? [], $this->pivotJson($pivot?->options));
        $image = $this->markerImage($point);
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
            'appearance' => $this->pointAppearance($markerStyle, $icon, $image, $color),
            'cluster' => [
                'enabled' => (bool) ($options['clusterable'] ?? true),
                'group' => $options['cluster_group'] ?? 'default',
            ],
            'options' => $options,
            'sortOrder' => $pivot?->sort_order ?? 0,
        ];
    }

    protected function pointAppearance(array $style, ?string $icon, ?string $image, ?string $color): array
    {
        $content = is_array($style['content'] ?? null) ? $style['content'] : [];
        $contentType = $content['type'] ?? config('filament-map.markers.content_type', 'icon');
        $contentValue = match ($contentType) {
            'image' => $image,
            'icon' => $content['value'] ?? $icon,
            'text' => $content['value'] ?? null,
            default => null,
        };

        return [
            'shape' => $style['shape'] ?? config('filament-map.markers.shape', 'pin'),
            'svg' => $style['svg'] ?? null,
            'content' => ['type' => $contentType, 'value' => $contentValue],
            'color' => $color,
            'size' => is_array($style['size'] ?? null) ? $style['size'] : [],
            'css' => is_array($style['css'] ?? null) ? $style['css'] : [],
        ];
    }

    protected function clustering(Map $map): array
    {
        $mapClustering = Arr::get($map->options ?? [], 'clustering', []);

        return array_replace_recursive(
            config('filament-map.clustering', []),
            is_array($mapClustering) ? $mapClustering : [],
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

    protected function markerImage(GeoPoint $point): ?string
    {
        $collection = config('filament-map.media_collections.marker_image', 'marker_image');
        $typeCollection = config('filament-map.media_collections.default_marker_image', 'default_marker_image');

        return $point->getFirstMediaUrl($collection) ?: $point->type?->getFirstMediaUrl($typeCollection) ?: null;
    }
}
