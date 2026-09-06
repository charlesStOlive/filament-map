<?php

namespace CharlesStOlive\FilamentMap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapSceneLayerAssignment extends Model
{
    protected $fillable = ['map_scene_id', 'map_layer_id', 'sort_order', 'is_visible_by_default', 'style', 'style_rules', 'options'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_visible_by_default' => 'boolean', 'style' => 'array', 'style_rules' => 'array', 'options' => 'array'];
    }

    public function getTable(): string
    {
        return config('filament-map.tables.scene_layers', 'filament_map_scene_layer');
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(MapScene::class, 'map_scene_id');
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(MapLayer::class, 'map_layer_id');
    }
}
