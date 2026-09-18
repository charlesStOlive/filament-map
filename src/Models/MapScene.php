<?php

namespace CharlesStOlive\FilamentMap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapScene extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'mode',
        'center_latitude', 'center_longitude', 'zoom', 'min_zoom', 'max_zoom', 'bounds',
        'options', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'center_latitude' => 'decimal:7', 'center_longitude' => 'decimal:7',
            'zoom' => 'integer', 'min_zoom' => 'integer', 'max_zoom' => 'integer',
            'bounds' => 'array', 'options' => 'array', 'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('filament-map.tables.scenes', 'filament_map_scenes');
    }

    public function layers(): BelongsToMany
    {
        return $this->belongsToMany(MapLayer::class, config('filament-map.tables.scene_layers', 'filament_map_scene_layer'))
            ->withPivot(['sort_order', 'is_visible_by_default', 'style', 'style_rules', 'options'])
            ->withTimestamps()->orderByPivot('sort_order')->orderBy('map_layer_id');
    }

    public function layerAssignments(): HasMany
    {
        return $this->hasMany(MapSceneLayerAssignment::class)->orderBy('sort_order');
    }
}
