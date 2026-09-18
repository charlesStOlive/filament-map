<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use CharlesStOlive\FilamentMap\Models\MapScene;
use Filament\Forms\Components\Field;

class MapLayerPreview extends Field
{
    protected string $view = 'filament-map::forms.components.map-layer-preview';

    protected string $sceneField = 'preview_scene_id';

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

    public function sceneField(string $field): static
    {
        $this->sceneField = $field;

        return $this;
    }

    public function height(string $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getSceneField(): string
    {
        return $this->sceneField;
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

    public function getPreviewScenes(): array
    {
        return MapScene::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'center_latitude', 'center_longitude', 'zoom', 'bounds'])
            ->mapWithKeys(fn (MapScene $scene): array => [
                $scene->getKey() => [
                    'name' => $scene->name,
                    'center' => [
                        'lat' => $scene->center_latitude !== null ? (float) $scene->center_latitude : (float) config('filament-map.default.lat'),
                        'lng' => $scene->center_longitude !== null ? (float) $scene->center_longitude : (float) config('filament-map.default.lng'),
                    ],
                    'zoom' => $scene->zoom ?? config('filament-map.default.zoom'),
                    'bounds' => $scene->bounds,
                ],
            ])
            ->all();
    }
}
