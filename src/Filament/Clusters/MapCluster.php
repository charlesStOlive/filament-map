<?php

namespace CharlesStOlive\FilamentMap\Filament\Clusters;

use BackedEnum;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;
use Filament\Clusters\Cluster;
use Filament\Panel;

class MapCluster extends Cluster
{
    /** Accessible (menu et adresse) dès qu'une de ses listes l'est — règle native de Filament. */
    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }
    protected static string | BackedEnum | null $navigationIcon = null;

    public static function getNavigationLabel(): string
    {
        return config('filament-map.cluster.label', 'Cartographie');
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return config('filament-map.cluster.slug', 'cartographie');
    }

    public static function getNavigationIcon(): string | BackedEnum | Htmlable | null
    {
        return config('filament-map.cluster.icon', 'heroicon-o-map');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-map.cluster.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-map.cluster.navigation_sort');
    }
}
