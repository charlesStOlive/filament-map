<?php

namespace CharlesStOlive\FilamentMap\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MapLayer extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'preview_map_id',
        'name',
        'key',
        'type',
        'source_type',
        'source_url',
        'source_path',
        'source_json',
        'style',
        'style_rules',
        'options',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'source_json' => 'array',
            'style' => 'array',
            'style_rules' => 'array',
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('filament-map.tables.layers', parent::getTable());
    }

    public function previewMap(): BelongsTo
    {
        return $this->belongsTo(Map::class, 'preview_map_id');
    }

    public function maps(): BelongsToMany
    {
        return $this->belongsToMany(Map::class, config('filament-map.tables.map_layers', 'filament_map_map_layer'))
            ->withPivot([
                'sort_order',
                'is_visible_by_default',
                'style',
                'style_rules',
                'options',
            ])
            ->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MapLayerAssignment::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(config('filament-map.media_collections.layer_source', 'layer_source'))->singleFile();
    }
}
