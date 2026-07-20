<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use CharlesStOlive\FilamentMap\Models\Map;
use Filament\Forms\Components\Field;

class MapLayerPreview extends Field
{
    protected string $view = 'filament-map::forms.components.map-layer-preview';

    protected string $mapField = 'preview_map_id';

    protected string $typeField = 'type';

    protected string $sourceTypeField = 'source_type';

    protected string $sourceUrlField = 'source_url';

    protected string $sourcePathField = 'source_path';

    protected string $sourceJsonField = 'source_json';

    protected string $styleField = 'style';

    protected string $styleRulesField = 'style_rules';

    protected string $optionsField = 'options';

    protected string $visibleField = 'is_visible_by_default';

    protected string $height = 'h-[420px]';

    public function mapField(string $field): static
    {
        $this->mapField = $field;

        return $this;
    }

    public function height(string $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getMapField(): string
    {
        return $this->mapField;
    }

    public function getTypeField(): string
    {
        return $this->typeField;
    }

    public function getSourceTypeField(): string
    {
        return $this->sourceTypeField;
    }

    public function getSourceUrlField(): string
    {
        return $this->sourceUrlField;
    }

    public function getSourcePathField(): string
    {
        return $this->sourcePathField;
    }

    public function getSourceJsonField(): string
    {
        return $this->sourceJsonField;
    }

    public function getStyleField(): string
    {
        return $this->styleField;
    }

    public function getStyleRulesField(): string
    {
        return $this->styleRulesField;
    }

    public function getOptionsField(): string
    {
        return $this->optionsField;
    }

    public function getVisibleField(): string
    {
        return $this->visibleField;
    }

    public function getHeight(): string
    {
        return $this->height;
    }

    public function getPreviewMaps(): array
    {
        return Map::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'center_latitude', 'center_longitude', 'zoom', 'bounds'])
            ->mapWithKeys(fn (Map $map): array => [
                $map->getKey() => [
                    'name' => $map->name,
                    'center' => [
                        'lat' => $map->center_latitude !== null ? (float) $map->center_latitude : (float) config('filament-map.default.lat'),
                        'lng' => $map->center_longitude !== null ? (float) $map->center_longitude : (float) config('filament-map.default.lng'),
                    ],
                    'zoom' => $map->zoom ?? config('filament-map.default.zoom'),
                    'bounds' => $map->bounds,
                ],
            ])
            ->all();
    }
}
