<?php

namespace CharlesStOlive\FilamentMap\Filament\Concerns;

use CharlesStOlive\FilamentMap\Support\PermissionManager;
use Illuminate\Database\Eloquent\Model;

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

    public static function canView(Model $record): bool
    {
        return static::canPerformSpecificAction('view');
    }

    public static function canEdit(Model $record): bool
    {
        return static::canPerformSpecificAction('edit');
    }

    public static function canDelete(Model $record): bool
    {
        return static::canPerformSpecificAction('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::canPerformSpecificAction('delete');
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::canPerformSpecificAction('delete');
    }

    public static function canForceDeleteAny(): bool
    {
        return static::canPerformSpecificAction('delete');
    }

    public static function canReplicate(Model $record): bool
    {
        return static::canPerformSpecificAction('create');
    }

    public static function canRestore(Model $record): bool
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
