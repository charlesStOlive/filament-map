<?php

namespace CharlesStOlive\FilamentMap\Filament\Concerns;

use CharlesStOlive\FilamentMap\Support\PermissionManager;

trait HasMapResourceAuthorization
{
    protected static function getPermissionPrefix(): string
    {
        return PermissionManager::resourcePrefix(static::class);
    }

    public static function canViewAny(): bool
    {
        return static::canPerformSpecificAction('viewany');
    }

    public static function canCreate(): bool
    {
        return static::canPerformSpecificAction('create');
    }

    public static function canView($record): bool
    {
        return static::canPerformSpecificAction('view');
    }

    public static function canEdit($record): bool
    {
        return static::canPerformSpecificAction('edit');
    }

    public static function canDelete($record): bool
    {
        return static::canPerformSpecificAction('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::canPerformSpecificAction('delete');
    }

    public static function canForceDelete($record): bool
    {
        return static::canPerformSpecificAction('delete');
    }

    public static function canForceDeleteAny(): bool
    {
        return static::canPerformSpecificAction('delete');
    }

    public static function canRestore($record): bool
    {
        return static::canPerformSpecificAction('edit');
    }

    public static function canRestoreAny(): bool
    {
        return static::canPerformSpecificAction('edit');
    }

    public static function canReorder(): bool
    {
        return static::canPerformSpecificAction('edit');
    }

    public static function canPerformSpecificAction(string $action): bool
    {
        return PermissionManager::can(static::getPermissionPrefix() . ".{$action}");
    }
}
