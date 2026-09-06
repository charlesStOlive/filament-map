<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\MapScenes\Pages;

use CharlesStOlive\FilamentMap\Filament\Resources\MapScenes\MapSceneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMapScenes extends ListRecords
{
    protected static string $resource = MapSceneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
