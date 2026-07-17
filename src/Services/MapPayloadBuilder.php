<?php

namespace CharlesStOlive\FilamentMap\Services;

use CharlesStOlive\FilamentMap\Models\GeoPoint;
use CharlesStOlive\FilamentMap\Models\Map;
use CharlesStOlive\FilamentMap\Models\MapLayer;
use Illuminate\Support\Arr;

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
            'controls' => [
                'zoom' => true,
                'layers' => true,
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }

    protected function map(Map $map): array
    {
        return [
            'id' => $map->getKey(),
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

    protected function layer(MapLayer $layer): array
    {
        return [
            'id' => $layer->getKey(),
            'mapId' => $layer->map_id,
            'name' => $layer->name,
            'key' => $layer->key,
            'type' => $layer->type,
            'visible' => $layer->is_visible_by_default,
            'source' => $this->layerSource($layer),
            'style' => $layer->style ?? [],
            'styleRules' => $layer->style_rules ?? [],
            'options' => $layer->options ?? [],
            'sortOrder' => $layer->sort_order,
        ];
    }

    protected function layerSource(MapLayer $layer): array
    {
        $media = $layer->getFirstMedia(config('filament-map.media_collections.layer_source', 'layer_source'));

        if ($media !== null) {
            return [
                'type' => 'url',
                'url' => $media->getUrl(),
            ];
        }

        return match ($layer->source_type) {
            'url' => ['type' => 'url', 'url' => $layer->source_url],
            'path' => ['type' => 'url', 'url' => $layer->source_path],
            'json' => ['type' => 'json', 'data' => $layer->source_json],
            default => ['type' => null],
        };
    }

    protected function point(GeoPoint $point): array
    {
        $pivot = $point->pivot;
        $type = $point->type;
        $markerStyle = array_replace_recursive(
            $type?->marker_style ?? [],
            $point->marker_style ?? [],
            Arr::wrap($pivot?->marker_style),
        );

        return [
            'id' => $point->getKey(),
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
            'icon' => $point->options['icon'] ?? $type?->icon,
            'color' => $point->options['color'] ?? $type?->color,
            'image' => $this->markerImage($point),
            'style' => $markerStyle,
            'options' => array_replace_recursive($point->options ?? [], Arr::wrap($pivot?->options)),
            'sortOrder' => $pivot?->sort_order ?? 0,
        ];
    }

    protected function markerImage(GeoPoint $point): ?string
    {
        $collection = config('filament-map.media_collections.marker_image', 'marker_image');
        $typeCollection = config('filament-map.media_collections.default_marker_image', 'default_marker_image');

        return $point->getFirstMediaUrl($collection) ?: $point->type?->getFirstMediaUrl($typeCollection) ?: null;
    }
}
