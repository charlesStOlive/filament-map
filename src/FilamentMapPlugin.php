<?php

namespace CharlesStOlive\FilamentMap;

use CharlesStOlive\FilamentMap\Filament\Clusters\MapCluster;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPoints\GeoPointResource;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\GeoPointTypeResource;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\MapLayerResource;
use CharlesStOlive\FilamentMap\Filament\Resources\MapScenes\MapSceneResource;
use CharlesStOlive\FilamentMap\Models\GeoPointType;
use Closure;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Throwable;

class FilamentMapPlugin implements Plugin
{
    /** @var (Closure(string, GeoPointType|null): array<int, Action>)|null */
    protected ?Closure $pointTypeActions = null;

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

    public function cluster(?string $cluster): static
    {
        config()->set('filament-map.cluster.enabled', $cluster !== null);
        config()->set('filament-map.cluster.class', $cluster);

        return $this;
    }

    /**
     * Des actions en plus dans l'en-tête des pages des types de points, fournies par l'application — par exemple une
     * demande IA qui dessine un SVG, sans que ce plugin connaisse l'IA. La closure reçoit la page (`list` : la liste
     * des types ; `edit` : la fiche d'un type) et, sur la fiche, le type ; elle rend les actions à y ajouter.
     *
     *     FilamentMapPlugin::make()->pointTypeActions(fn (string $page, ?GeoPointType $type): array => [...])
     *
     * @param  (Closure(string, GeoPointType|null): array<int, Action>)|null  $callback
     */
    public function pointTypeActions(?Closure $callback): static
    {
        $this->pointTypeActions = $callback;

        return $this;
    }

    /** @return array<int, Action> Les actions ajoutées par l'application à cette page (voir pointTypeActions()). */
    public static function pointTypeActionsFor(string $page, ?GeoPointType $type = null): array
    {
        try {
            $callback = static::get()->pointTypeActions;
        } catch (Throwable) {
            // Le plugin n'est pas sur ce panneau : rien à ajouter.
            return [];
        }

        return $callback === null ? [] : array_values(array_filter($callback($page, $type)));
    }

    public function register(Panel $panel): void
    {
        $resources = [];

        if (config('filament-map.resources.scenes', true)) {
            $resources[] = MapSceneResource::class;
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

        if (
            config('filament-map.cluster.enabled', true)
            && config('filament-map.cluster.class', MapCluster::class) === MapCluster::class
        ) {
            $panel->discoverClusters(
                in: __DIR__.'/Filament/Clusters',
                for: 'CharlesStOlive\\FilamentMap\\Filament\\Clusters',
            );
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
