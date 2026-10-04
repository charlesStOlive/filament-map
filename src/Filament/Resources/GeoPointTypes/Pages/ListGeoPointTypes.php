<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\Pages;

use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\GeoPointTypeResource;
use CharlesStOlive\FilamentMap\FilamentMapPlugin;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeoPointTypes extends ListRecords
{
    protected static string $resource = GeoPointTypeResource::class;

    protected function getHeaderActions(): array
    {
        // Avec celles que l'application ajoute (FilamentMapPlugin::pointTypeActions()).
        return [...FilamentMapPlugin::pointTypeActionsFor('list'), CreateAction::make()];
    }
}
