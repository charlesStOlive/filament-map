<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class MapViewportPicker extends Field
{
    protected string $view = 'filament-map::forms.components.map-viewport-picker';

    protected string $latitudeField = 'center_latitude';

    protected string $longitudeField = 'center_longitude';

    protected string $zoomField = 'zoom';

    protected string $boundsField = 'bounds';

    protected string $height = 'h-[420px]';

    protected bool $syncBounds = true;

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

    public function zoomField(string $field): static
    {
        $this->zoomField = $field;

        return $this;
    }

    public function boundsField(string $field): static
    {
        $this->boundsField = $field;

        return $this;
    }

    public function height(string $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function syncBounds(bool $condition = true): static
    {
        $this->syncBounds = $condition;

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

    public function getZoomField(): string
    {
        return $this->zoomField;
    }

    public function getBoundsField(): string
    {
        return $this->boundsField;
    }

    public function getHeight(): string
    {
        return $this->height;
    }

    public function shouldSyncBounds(): bool
    {
        return $this->syncBounds;
    }
}
