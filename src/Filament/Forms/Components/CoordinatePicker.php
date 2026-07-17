<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class CoordinatePicker extends Field
{
    protected string $view = 'filament-map::forms.components.coordinate-picker';

    protected string $latitudeField = 'latitude';

    protected string $longitudeField = 'longitude';

    protected ?string $mapField = null;

    public function latitudeField(string $field): static
    {
        $this->latitudeField = $field;

        return $this;
    }

    public function longitudeField(string $field): static
    {
        $this->longitudeField = $field;

        return $this;
    }

    public function mapField(?string $field): static
    {
        $this->mapField = $field;

        return $this;
    }

    public function getLatitudeField(): string
    {
        return $this->latitudeField;
    }

    public function getLongitudeField(): string
    {
        return $this->longitudeField;
    }

    public function getMapField(): ?string
    {
        return $this->mapField;
    }
}
