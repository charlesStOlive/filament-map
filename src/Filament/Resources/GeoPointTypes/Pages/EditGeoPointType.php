<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\Pages;

use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\GeoPointTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGeoPointType extends EditRecord
{
    protected static string $resource = GeoPointTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
