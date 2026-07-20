<?php

namespace CharlesStOlive\FilamentMap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapLayerAssignment extends Model
{
    protected $fillable = [
        'map_id',
        'map_layer_id',
        'sort_order',
        'is_visible_by_default',
        'style',
        'style_rules',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_visible_by_default' => 'boolean',
            'style' => 'array',
            'style_rules' => 'array',
            'options' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('filament-map.tables.map_layers', parent::getTable());
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class);
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(MapLayer::class, 'map_layer_id');
    }
}
