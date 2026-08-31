<?php

namespace CharlesStOlive\FilamentMap\Enums;

enum GeoPointActionTrigger: string
{
    case Load = 'load';
    case Click = 'click';
    case DoubleClick = 'double_click';
    case MouseEnter = 'mouse_enter';
    case MouseLeave = 'mouse_leave';
    case Event = 'event';

    public static function options(): array
    {
        return [
            self::Load->value => 'Au chargement de la carte',
            self::Click->value => 'Au clic',
            self::DoubleClick->value => 'Au double clic',
            self::MouseEnter->value => 'Au survol',
            self::MouseLeave->value => 'A la fin du survol',
            self::Event->value => 'A la reception d’un evenement',
        ];
    }
}
