<?php

namespace CharlesStOlive\FilamentMap\Enums;

enum GeoPointActionType: string
{
    case Dispatch = 'dispatch';
    case Show = 'show';
    case Hide = 'hide';
    case Toggle = 'toggle';
    case OpenPopup = 'open_popup';
    case ClosePopup = 'close_popup';
    case Navigate = 'navigate';

    public static function options(): array
    {
        return [
            self::Dispatch->value => 'Emettre un evenement',
            self::Show->value => 'Afficher le point',
            self::Hide->value => 'Masquer le point',
            self::Toggle->value => 'Basculer la visibilite',
            self::OpenPopup->value => 'Ouvrir une popup',
            self::ClosePopup->value => 'Fermer la popup',
            self::Navigate->value => 'Ouvrir une navigation',
        ];
    }
}
