<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\GeoPoints\Pages;

use CharlesStOlive\FilamentMap\Filament\Concerns\HasContextualReturnAction;
use CharlesStOlive\FilamentMap\Filament\Resources\GeoPoints\GeoPointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGeoPoint extends EditRecord
{
    use HasContextualReturnAction;

    protected static string $resource = GeoPointResource::class;

    protected function getHeaderActions(): array
    {
        return array_filter([$this->contextualReturnAction(), DeleteAction::make()]);
    }
}
