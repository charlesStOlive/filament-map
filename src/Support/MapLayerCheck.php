<?php

namespace CharlesStOlive\FilamentMap\Support;

use CharlesStOlive\FilamentMap\Models\MapLayer;

/** Le résultat de la vérification d'une couche : un état (MapLayer::CHECK_*) et sa raison, en une phrase. */
final class MapLayerCheck
{
    public function __construct(
        public readonly string $status,
        public readonly string $message,
    ) {}

    public static function ok(string $message): self
    {
        return new self(MapLayer::CHECK_OK, $message);
    }

    public static function warning(string $message): self
    {
        return new self(MapLayer::CHECK_WARNING, $message);
    }

    public static function error(string $message): self
    {
        return new self(MapLayer::CHECK_ERROR, $message);
    }

    /** Une réserve ajoutée à un résultat qui fonctionne : il passe en avertissement. */
    public function withWarning(?string $warning): self
    {
        if (! filled($warning) || $this->status === MapLayer::CHECK_ERROR) {
            return $this;
        }

        return self::warning(trim($this->message.' '.$warning));
    }

    public static function label(?string $status): string
    {
        return match ($status) {
            MapLayer::CHECK_OK => 'Fonctionne',
            MapLayer::CHECK_WARNING => 'À surveiller',
            MapLayer::CHECK_ERROR => 'En erreur',
            default => 'Non vérifiée',
        };
    }

    public static function color(?string $status): string
    {
        return match ($status) {
            MapLayer::CHECK_OK => 'success',
            MapLayer::CHECK_WARNING => 'warning',
            MapLayer::CHECK_ERROR => 'danger',
            default => 'gray',
        };
    }

    public static function icon(?string $status): string
    {
        return match ($status) {
            MapLayer::CHECK_OK => 'heroicon-o-check-circle',
            MapLayer::CHECK_WARNING => 'heroicon-o-exclamation-triangle',
            MapLayer::CHECK_ERROR => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }
}
