<?php

namespace CharlesStOlive\FilamentMap\Services;

use CharlesStOlive\FilamentMap\Models\MapLayer;
use CharlesStOlive\FilamentMap\Models\MapScene;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class MapScenePayloadBuilder
{
    public function __construct(private readonly MapPayloadBuilder $maps) {}

    public function build(MapScene $scene, array $overrides = []): ?array
    {
        $scene->loadMissing(['map', 'layers']);

        if (! $scene->is_active || ! $scene->map?->is_active) {
            return null;
        }

        if (array_key_exists('layers', $overrides)) {
            throw ValidationException::withMessages(['layers' => 'Les couches se configurent dans la scène cartographique.']);
        }

        $map = clone $scene->map;
        // A scene never inherits the standalone map's points or layer composition.
        $map->setRelation('layers', collect());
        $map->setRelation('points', collect());
        $payload = $this->maps->build($map, Arr::only($overrides, ['points', 'controls', 'state']));
        $payload['layers'] = $scene->layers->where('is_active', true)->values()
            ->map(fn (MapLayer $layer): array => $this->maps->layer($layer))->all();
        $payload['scene'] = ['id' => $scene->getKey(), 'key' => $scene->slug, 'mapId' => $scene->map_id];
        $payload['map'] = array_replace_recursive($payload['map'], [
            'id' => $scene->getKey(),
            'key' => $scene->slug,
            'name' => $scene->name,
            'center' => [
                'lat' => $scene->center_latitude !== null ? (float) $scene->center_latitude : $payload['map']['center']['lat'],
                'lng' => $scene->center_longitude !== null ? (float) $scene->center_longitude : $payload['map']['center']['lng'],
            ],
            'zoom' => max($payload['map']['minZoom'], min($payload['map']['maxZoom'], $scene->zoom ?? $payload['map']['zoom'])),
            'options' => Arr::except(array_replace_recursive($payload['map']['options'], $scene->options ?? []), ['minZoom', 'maxZoom', 'center', 'zoom']),
        ], Arr::only($overrides['map'] ?? [], ['id']));

        return $payload;
    }
}
