<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\GeoPoints\Pages;

use CharlesStOlive\FilamentMap\Filament\Resources\GeoPoints\GeoPointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeoPoints extends ListRecords
{
    protected static string $resource = GeoPointResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
