<?php

namespace CharlesStOlive\FilamentMap\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MapLayer extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'preview_scene_id',
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
        'check_status',
        'check_message',
        'checked_at',
    ];

    /** La vérification de la couche (Services\MapLayerChecker) : elle fonctionne, fonctionne avec une réserve, ou échoue. */
    public const CHECK_OK = 'ok';

    public const CHECK_WARNING = 'warning';

    public const CHECK_ERROR = 'error';

    protected function casts(): array
    {
        return [
            'source_json' => 'array',
            'style' => 'array',
            'style_rules' => 'array',
            'options' => 'array',
            'is_active' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Un fond n'a qu'une source possible : son URL.
        static::saving(function (MapLayer $layer): void {
            if (in_array($layer->type, ['style', 'tile'], true)) {
                $layer->source_type = 'url';
            }
        });
    }

    public function getTable(): string
    {
        return config('filament-map.tables.layers', parent::getTable());
    }

    public function previewScene(): BelongsTo
    {
        return $this->belongsTo(MapScene::class, 'preview_scene_id');
    }

    public function scenes(): BelongsToMany
    {
        return $this->belongsToMany(MapScene::class, config('filament-map.tables.scene_layers', 'filament_map_scene_layer'))
            ->withPivot(['sort_order', 'is_visible_by_default', 'style', 'style_rules', 'options'])->withTimestamps();
    }

    /**
     * Les natures de couche, dans l'ordre du formulaire. Un fond (`style`, `tile`) remplit toute la carte ; les autres se
     * posent dessus.
     *
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            'style' => 'Fond de carte vectoriel (style.json)',
            'tile' => 'Fond de carte en tuiles images ({z}/{x}/{y})',
            'geojson' => 'Tracés et zones (GeoJSON)',
            'points' => 'Points (GeoJSON)',
            'svg_overlay' => 'Image SVG calée sur la carte',
            'custom' => 'Personnalisée (rendu propre au projet)',
        ];
    }

    /**
     * Le type rendu par la carte. Une ancienne couche « tuiles » qui portait son style dans `options.style_url` est un fond
     * vectoriel.
     */
    public function renderType(): ?string
    {
        return $this->type === 'tile' && filled($this->legacyStyleUrl()) ? 'style' : $this->type;
    }

    public function isBaseMap(): bool
    {
        return in_array($this->renderType(), ['style', 'tile'], true);
    }

    /** L'adresse d'un fond : son URL source, ou, pour une ancienne couche, `options.style_url`. */
    public function baseMapUrl(): ?string
    {
        if ($this->type === 'tile' && filled($this->legacyStyleUrl())) {
            return $this->legacyStyleUrl();
        }

        return $this->source_url ?: ($this->type === 'style' ? $this->legacyStyleUrl() : null);
    }

    protected function legacyStyleUrl(): ?string
    {
        $options = $this->options ?? [];

        return $options['style_url'] ?? $options['styleUrl'] ?? null;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(config('filament-map.media_collections.layer_source', 'layer_source'))->singleFile();
    }
}
