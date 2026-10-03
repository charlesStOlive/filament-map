<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\Pages;

use CharlesStOlive\FilamentMap\Filament\Concerns\HasContextualReturnAction;
use CharlesStOlive\FilamentMap\Filament\Resources\MapLayers\MapLayerResource;
use CharlesStOlive\FilamentMap\Services\MapLayerChecker;
use CharlesStOlive\FilamentMap\Support\MapLayerCheck;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMapLayer extends CreateRecord
{
    use HasContextualReturnAction;

    protected static string $resource = MapLayerResource::class;

    protected ?MapLayerCheck $layerCheck = null;

    public function mount(): void
    {
        $this->captureContextualCreation();

        parent::mount();
    }

    protected function afterCreate(): void
    {
        $this->layerCheck = app(MapLayerChecker::class)->checkAndStore($this->record);

        $this->dispatchContextualResourceCreated($this->record);
    }

    /** L'enregistrement dit aussitôt si la couche fonctionne. */
    protected function getCreatedNotification(): ?Notification
    {
        return $this->layerCheck
            ? MapLayerResource::checkNotification($this->layerCheck, $this->record->name, 'Couche créée : '.mb_strtolower(MapLayerCheck::label($this->layerCheck->status)))
            : parent::getCreatedNotification();
    }

    protected function getRedirectUrl(): string
    {
        return $this->contextualRedirectUrl(parent::getRedirectUrl());
    }

    protected function getHeaderActions(): array
    {
        return array_filter([$this->contextualReturnAction()]);
    }
}
