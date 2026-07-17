<?php

namespace CharlesStOlive\FilamentMap\Support;

class PermissionManager
{
    public const SERVICE = 'CharlesStOlive\\FilamentPermissionManager\\Services\\PermissionService';

    public static function can(string $permission): bool
    {
        if (! config('filament-map.authorization.enabled', true)) {
            return true;
        }

        if (! class_exists(self::SERVICE)) {
            return (bool) config('filament-map.authorization.allow_without_permission_manager', true);
        }

        return (bool) (self::SERVICE)::can($permission);
    }

    public static function resourcePrefix(string $resourceClass): string
    {
        $resourceName = strtolower(str_replace('Resource', '', class_basename($resourceClass)));
        $clusterName = static::clusterName($resourceClass::getCluster());

        return $clusterName ? "{$clusterName}.{$resourceName}" : $resourceName;
    }

    public static function clusterName(?string $clusterClass): ?string
    {
        if ($configured = config('filament-map.authorization.permission_cluster')) {
            return $configured;
        }

        if ($clusterClass === null) {
            return null;
        }

        if (method_exists($clusterClass, 'getSlug') && $clusterClass::getSlug()) {
            return $clusterClass::getSlug();
        }

        return strtolower(str_replace('Cluster', '', class_basename($clusterClass)));
    }

    public static function clusterPermissionName(string $clusterClass): string
    {
        return config('filament-map.authorization.permission_cluster')
            ?: static::clusterName($clusterClass)
            ?: strtolower(str_replace('Cluster', '', class_basename($clusterClass)));
    }
}
