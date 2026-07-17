<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\Pages;

use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\GeoPointTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeoPointTypes extends ListRecords
{
    protected static string $resource = GeoPointTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
