<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\Maps\Pages;

use CharlesStOlive\FilamentMap\Filament\Concerns\HasContextualReturnAction;
use CharlesStOlive\FilamentMap\Filament\Resources\Maps\MapResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMap extends CreateRecord
{
    use HasContextualReturnAction;

    protected static string $resource = MapResource::class;

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
