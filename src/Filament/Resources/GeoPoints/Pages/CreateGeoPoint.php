<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\GeoPoints\Pages;

use CharlesStOlive\FilamentMap\Filament\Concerns\HasContextualReturnAction;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPoints\GeoPointResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGeoPoint extends CreateRecord
{
    use HasContextualReturnAction;

    protected static string $resource = GeoPointResource::class;

    public function mount(): void
    {
        $this->captureContextualCreation();

        parent::mount();
    }

    protected function afterCreate(): void
    {
        $this->dispatchContextualResourceCreated($this->record);
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
