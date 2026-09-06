<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\MapScenes\Pages;

use CharlesStOlive\FilamentMap\Filament\Concerns\HasContextualReturnAction;
use CharlesStOlive\FilamentMap\Filament\Resources\MapScenes\MapSceneResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMapScene extends EditRecord
{
    use HasContextualReturnAction;

    protected static string $resource = MapSceneResource::class;

    protected function getHeaderActions(): array
    {
        return array_filter([
            $this->contextualReturnAction(),
            DeleteAction::make(),
        ]);
    }
}
