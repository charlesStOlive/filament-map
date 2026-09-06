<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use CharlesStOlive\FilamentMap\Models\Map;
use CharlesStOlive\FilamentMap\Models\MapScene;
use CharlesStOlive\FilamentMap\Services\MapPayloadBuilder;
use CharlesStOlive\FilamentMap\Services\MapScenePayloadBuilder;
use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Container\Container;

class MapViewportPicker extends Field
{
    public const TYPE_COORDINATE = 'coordinate';

    public const TYPE_VIEWPORT = 'viewport';

    protected string $view = 'filament-map::forms.components.map-viewport-picker';

    protected Map|int|string|Closure|null $map = null;

    protected MapScene|int|string|Closure|null $scene = null;

    public function scene(MapScene|int|string|Closure|null $scene): static
    {
        $this->scene = $scene;

        return $this;
    }

    protected string $type = self::TYPE_VIEWPORT;

    protected string $latitudeField = 'center_latitude';

    protected string $longitudeField = 'center_longitude';

    protected string $zoomField = 'zoom';

    protected string $boundsField = 'bounds';

    protected string $height = 'h-[420px]';

    protected bool $syncBounds = true;

    public function map(Map|int|string|Closure|null $map): static
    {
        $this->map = $map;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function coordinate(): static
    {
        return $this->type(self::TYPE_COORDINATE)->syncBounds(false);
    }

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

    public function getType(): string
    {
        return $this->type;
    }

    public function getMapPayload(): ?array
    {
        $scene = $this->evaluate($this->scene);

        if ($scene !== null && $scene !== '') {
            $scene = $scene instanceof MapScene ? $scene : MapScene::query()->find($scene);

            return $scene ? app(MapScenePayloadBuilder::class)->build($scene) : null;
        }

        $map = $this->evaluate($this->map);

        if (! $map instanceof Map) {
            $map = ($map !== null && $map !== '') ? Map::query()->find($map) : null;
        }

        if (! $map) {
            return null;
        }

        $overrides = $this->type === self::TYPE_COORDINATE
            ? ['points' => []]
            : [];

        return Container::getInstance()->make(MapPayloadBuilder::class)->build($map, $overrides);
    }
}
