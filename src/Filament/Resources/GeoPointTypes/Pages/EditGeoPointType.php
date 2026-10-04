<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\Pages;

use CharlesStOlive\FilamentMap\Filament\Concerns\HasContextualReturnAction;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPointTypes\GeoPointTypeResource;
use CharlesStOlive\FilamentMap\FilamentMapPlugin;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGeoPointType extends EditRecord
{
    use HasContextualReturnAction;

    protected static string $resource = GeoPointTypeResource::class;

    protected function getHeaderActions(): array
    {
        // Avec celles que l'application ajoute (FilamentMapPlugin::pointTypeActions()).
        return array_filter([
            $this->contextualReturnAction(),
            ...FilamentMapPlugin::pointTypeActionsFor('edit', $this->getRecord()),
            GeoPointTypeResource::replicateAction(),
            DeleteAction::make(),
        ]);
    }
}
