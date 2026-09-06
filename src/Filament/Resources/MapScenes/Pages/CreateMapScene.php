<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\MapScenes\Pages;

use CharlesStOlive\FilamentMap\Filament\Concerns\HasContextualReturnAction;
use CharlesStOlive\FilamentMap\Filament\Resources\MapScenes\MapSceneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMapScene extends CreateRecord
{
    use HasContextualReturnAction;

    protected static string $resource = MapSceneResource::class;

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
