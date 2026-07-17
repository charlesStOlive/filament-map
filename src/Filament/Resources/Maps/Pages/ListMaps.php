<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\Maps\Pages;

use CharlesStOlive\FilamentMap\Filament\Resources\Maps\MapResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaps extends ListRecords
{
    protected static string $resource = MapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
