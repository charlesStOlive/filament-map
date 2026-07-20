<?php

namespace CharlesStOlive\FilamentMap\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Map extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'mode',
        'center_latitude',
        'center_longitude',
        'zoom',
        'min_zoom',
        'max_zoom',
        'bounds',
        'options',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'center_latitude' => 'decimal:7',
            'center_longitude' => 'decimal:7',
            'zoom' => 'integer',
            'min_zoom' => 'integer',
            'max_zoom' => 'integer',
            'bounds' => 'array',
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('filament-map.tables.maps', parent::getTable());
    }

    public function layers(): BelongsToMany
    {
        return $this->belongsToMany(MapLayer::class, config('filament-map.tables.map_layers', 'filament_map_map_layer'))
            ->withPivot([
                'sort_order',
                'is_visible_by_default',
                'style',
                'style_rules',
                'options',
            ])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function layerAssignments(): HasMany
    {
        return $this->hasMany(MapLayerAssignment::class)
            ->orderBy('sort_order');
    }

    public function points(): BelongsToMany
    {
        return $this->belongsToMany(GeoPoint::class, config('filament-map.tables.map_geo_point', 'filament_map_geo_map_point'))
            ->withPivot([
                'layer_id',
                'sort_order',
                'is_visible_by_default',
                'label',
                'tooltip',
                'popup_content',
                'marker_style',
                'options',
            ])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(config('filament-map.media_collections.map_preview', 'map_preview'))->singleFile();
    }
}
