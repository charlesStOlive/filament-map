<?php

namespace CharlesStOlive\FilamentMap\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class GeoPoint extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'geo_point_type_id',
        'name',
        'slug',
        'description',
        'latitude',
        'longitude',
        'coordinates',
        'marker_style',
        'options',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'marker_style' => 'array',
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('filament-map.tables.geo_points', parent::getTable());
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(GeoPointType::class, 'geo_point_type_id');
    }

    public function maps(): BelongsToMany
    {
        return $this->belongsToMany(Map::class, config('filament-map.tables.map_geo_point', 'filament_map_geo_map_point'))
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
            ->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(config('filament-map.media_collections.marker_image', 'marker_image'))->singleFile();
    }
}
