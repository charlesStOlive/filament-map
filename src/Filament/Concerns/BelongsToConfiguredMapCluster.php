<?php

namespace CharlesStOlive\FilamentMap\Filament\Concerns;

use CharlesStOlive\FilamentMap\Filament\Clusters\MapCluster;

trait BelongsToConfiguredMapCluster
{
    public static function getCluster(): ?string
    {
        if (! config('filament-map.cluster.enabled', true)) {
            return null;
        }

        return config('filament-map.cluster.class', MapCluster::class);
    }
}
