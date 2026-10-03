<?php

namespace CharlesStOlive\FilamentMap\Models;

use CharlesStOlive\FilamentMap\Support\MarkerShapes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class GeoPointType extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'key',
        'description',
        'icon',
        'color',
        'marker_style',
        'options',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'marker_style' => 'array',
            'options' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('filament-map.tables.geo_point_types', parent::getTable());
    }

    /** Ce que le marqueur montre dans sa forme : `none`, `icon`, `image` ou `text` (réglage du type, sinon du plugin). */
    public function contentType(): string
    {
        return $this->marker_style['content']['type'] ?? config('filament-map.markers.content_type', 'icon');
    }

    /** Sa forme a-t-elle une zone de contenu (`data-slot`, voir MarkerSvg) ? Les formes fournies en ont toutes une. */
    public function hasContentSlot(): bool
    {
        return MarkerShapes::resolve($this->marker_style ?? [])['slot'] !== null;
    }

    /**
     * Le point de ce type montre-t-il une image (une mini-vignette) ? Seulement si son contenu est « Image » et que sa
     * forme a une zone où la poser. Une image qu'on lui propose (MapPayloadBuilder::point()) est sinon ignorée : un type
     * qui n'est qu'une forme reste une forme.
     */
    public function acceptsImage(): bool
    {
        return $this->contentType() === 'image' && $this->hasContentSlot();
    }

    public function points(): HasMany
    {
        return $this->hasMany(GeoPoint::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(config('filament-map.media_collections.default_marker_image', 'default_marker_image'))->singleFile();
    }
}
