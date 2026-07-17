<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\Pages;

use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\MapLayerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMapLayers extends ListRecords
{
    protected static string $resource = MapLayerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
