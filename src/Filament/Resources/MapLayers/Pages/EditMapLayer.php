<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\Pages;

use CharlesStOlive\FilamentMap\Filament\Concerns\HasContextualReturnAction;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\MapLayerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMapLayer extends EditRecord
{
    use HasContextualReturnAction;

    protected static string $resource = MapLayerResource::class;

    protected function getHeaderActions(): array
    {
        return array_filter([$this->contextualReturnAction(), DeleteAction::make()]);
    }
}
