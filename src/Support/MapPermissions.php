<?php

namespace CharlesStOlive\FilamentMap\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Les actions propres des listes cartographiques (joindre des images, voir l'aperçu d'une couche) : réservées à qui en
 * a le droit quand l'application gère les permissions. Elle définit alors une ability Gate `{classe de la Resource}.{action}`
 * (avec filament-permission-manager : permission `{liste}.{action}`, déclarée par `$specificPermissions`). Sans elle,
 * l'action reste ouverte à quiconque voit la liste — aucune dépendance à un gestionnaire de permissions.
 *
 * Les actions de base (voir, créer, modifier, supprimer) passent par la policy du modèle : avec
 * filament-permission-manager, sa policy de repli (`{liste}.viewany`…).
 */
final class MapPermissions
{
    public static function allows(string $resource, string $action, ?Model $record = null): bool
    {
        $ability = "{$resource}.{$action}";

        return ! Gate::has($ability) || Gate::allows($ability, $record === null ? [] : [$record]);
    }
}
