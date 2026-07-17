<?php

namespace CharlesStOlive\FilamentMap\Filament\Concerns;

use CharlesStOlive\FilamentMap\Support\PermissionManager;

trait HasMapClusterAuthorization
{
    public static function canAccess(): bool
    {
        return PermissionManager::can(static::clusterPermission() . '.*');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    protected static function clusterPermission(): string
    {
        return PermissionManager::clusterPermissionName(static::class);
    }
}
