<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\Pages;

use CharlesStOlive\FilamentMap\Filament\Concerns\HasContextualReturnAction;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\MapLayerResource;
use CharlesStOlive\FilamentMap\Services\MapLayerChecker;
use CharlesStOlive\FilamentMap\Support\MapLayerCheck;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMapLayer extends EditRecord
{
    use HasContextualReturnAction;

    protected static string $resource = MapLayerResource::class;

    protected ?MapLayerCheck $layerCheck = null;

    protected function getHeaderActions(): array
    {
        return array_filter([$this->contextualReturnAction(), MapLayerResource::checkAction(), DeleteAction::make()]);
    }

    protected function afterSave(): void
    {
        $this->layerCheck = app(MapLayerChecker::class)->checkAndStore($this->record);
    }

    /** L'enregistrement dit aussitôt si la couche fonctionne. */
    protected function getSavedNotification(): ?Notification
    {
        return $this->layerCheck
            ? MapLayerResource::checkNotification($this->layerCheck, $this->record->name, 'Enregistré : '.mb_strtolower(MapLayerCheck::label($this->layerCheck->status)))
            : parent::getSavedNotification();
    }
}
