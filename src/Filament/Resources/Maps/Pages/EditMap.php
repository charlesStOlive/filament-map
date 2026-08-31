<?php

namespace CharlesStOlive\FilamentMap\Filament\Resources\Maps\Pages;

use CharlesStOlive\FilamentMap\Filament\Concerns\HasContextualReturnAction;
use CharlesStOlive\FilamentMap\Filament\Resources\Maps\MapResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMap extends EditRecord
{
    use HasContextualReturnAction;

    protected static string $resource = MapResource::class;

    protected function getHeaderActions(): array
    {
        return array_filter([
            $this->contextualReturnAction(),
            DeleteAction::make(),
        ]);
    }
}
