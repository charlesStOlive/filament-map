<?php

namespace CharlesStOlive\FilamentMap;

use CharlesStOlive\FilamentMap\Filament\Resources\GeoPoints\GeoPointResource;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\GeoPointTypeResource;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\MapLayerResource;
use CharlesStOlive\FilamentMap\Filament\Resources\Maps\MapResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentMapPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-map';
    }

    public function register(Panel $panel): void
    {
        $resources = [];

        if (config('filament-map.resources.maps', true)) {
            $resources[] = MapResource::class;
        }

        if (config('filament-map.resources.layers', true)) {
            $resources[] = MapLayerResource::class;
        }

        if (config('filament-map.resources.geo_points', true)) {
            $resources[] = GeoPointResource::class;
        }

        if (config('filament-map.resources.geo_point_types', true)) {
            $resources[] = GeoPointTypeResource::class;
        }

        $panel->resources($resources);
        $panel->discoverClusters(
            in: __DIR__ . '/Filament/Clusters',
            for: 'CharlesStOlive\\FilamentMap\\Filament\\Clusters',
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
