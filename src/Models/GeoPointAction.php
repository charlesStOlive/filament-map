<?php

namespace CharlesStOlive\FilamentMap\Models;

use CharlesStOlive\FilamentMap\Enums\GeoPointActionTrigger;
use CharlesStOlive\FilamentMap\Enums\GeoPointActionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoPointAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'geo_point_id',
        'name',
        'key',
        'trigger',
        'trigger_event',
        'type',
        'target',
        'payload',
        'options',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'trigger' => GeoPointActionTrigger::class,
            'type' => GeoPointActionType::class,
            'payload' => 'array',
            'options' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('filament-map.tables.geo_point_actions', parent::getTable());
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(GeoPoint::class, 'geo_point_id');
    }
}
