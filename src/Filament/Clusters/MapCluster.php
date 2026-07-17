<?php

namespace CharlesStOlive\FilamentMap\Filament\Clusters;

use BackedEnum;
use CharlesStOlive\FilamentMap\Filament\Concerns\HasMapClusterAuthorization;
use Filament\Clusters\Cluster;

class MapCluster extends Cluster
{
    use HasMapClusterAuthorization;
    protected static string | BackedEnum | null $navigationIcon = null;

    public static function getNavigationLabel(): string
    {
        return config('filament-map.cluster.label', 'Cartographie');
    }

    public static function getSlug(): string
    {
        return config('filament-map.cluster.slug', 'cartographie');
    }

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return config('filament-map.cluster.icon', 'heroicon-o-map');
    }

    public static function getNavigationGroup(): string | null
    {
        return config('filament-map.cluster.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-map.cluster.navigation_sort');
    }
}
