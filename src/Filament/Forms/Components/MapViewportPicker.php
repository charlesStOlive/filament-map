<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use CharlesStOlive\FilamentMap\Models\MapScene;
use CharlesStOlive\FilamentMap\Services\MapPayloadBuilder;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Utilities\Set;

class MapViewportPicker extends Field
{
    public const TYPE_COORDINATE = 'coordinate';

    public const TYPE_VIEWPORT = 'viewport';

    protected string $view = 'filament-map::forms.components.map-viewport-picker';

    protected MapScene|int|string|Closure|null $scene = null;

    public function scene(MapScene|int|string|Closure|null $scene): static
    {
        $this->scene = $scene;

        return $this;
    }

    protected string $type = self::TYPE_VIEWPORT;

    protected string $latitudeField = 'center_latitude';

    protected string $longitudeField = 'center_longitude';

    protected string $zoomField = 'zoom';

    protected string $boundsField = 'bounds';

    protected string $height = 'h-[420px]';

    protected bool $syncBounds = true;

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function coordinate(): static
    {
        return $this->type(self::TYPE_COORDINATE)->syncBounds(false);
    }

    public function latitudeField(string $field): static
    {
        $this->latitudeField = $field;

        return $this;
    }

    public function longitudeField(string $field): static
    {
        $this->longitudeField = $field;

        return $this;
    }

    public function zoomField(string $field): static
    {
        $this->zoomField = $field;

        return $this;
    }

    public function boundsField(string $field): static
    {
        $this->boundsField = $field;

        return $this;
    }

    public function height(string $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function syncBounds(bool $condition = true): static
    {
        $this->syncBounds = $condition;

        return $this;
    }

    public function getLatitudeField(): string
    {
        return $this->latitudeField;
    }

    public function getLongitudeField(): string
    {
        return $this->longitudeField;
    }

    public function getZoomField(): string
    {
        return $this->zoomField;
    }

    public function getBoundsField(): string
    {
        return $this->boundsField;
    }

    public function getHeight(): string
    {
        return $this->height;
    }

    public function shouldSyncBounds(): bool
    {
        return $this->syncBounds;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMapPayload(): ?array
    {
        $scene = $this->evaluate($this->scene);

        if (! $scene instanceof MapScene) {
            $scene = ($scene !== null && $scene !== '') ? MapScene::query()->find($scene) : null;
        }

        if (! $scene) {
            return null;
        }

        $overrides = $this->type === self::TYPE_COORDINATE
            ? ['points' => []]
            : [];

        return app(MapPayloadBuilder::class)->build($scene, $overrides);
    }

    /**
     * Bouton à poser en `->suffixAction()` d'un champ de zoom : récupère le
     * zoom actuel de la vue interactive la plus proche (même section) et le
     * pose dans le champ ciblé, sans aller-retour serveur pour lire la carte.
     */
    public static function captureZoomAction(string $targetField, string $label = 'Utiliser le zoom actuel'): Action
    {
        $actionName = 'capture-zoom-'.$targetField;
        $script = <<<JS
            const root = \$el.closest('.fi-section, .fi-modal-window, form');
            const mapEl = root?.querySelector('[id^="filament-map-viewport-picker-"]');
            const zoom = mapEl ? window.FilamentMap?.instances?.get(mapEl.id)?.map?.getZoom() : null;
            if (typeof zoom === 'number') { \$wire.mountAction('{$actionName}', { zoom: Math.round(zoom) }); }
            JS;

        return Action::make($actionName)
            ->label($label)
            ->tooltip($label)
            ->icon('heroicon-o-viewfinder-circle')
            ->livewireClickHandlerEnabled(false)
            ->extraAttributes(['x-on:click' => $script])
            ->action(function (array $arguments, Set $set) use ($targetField): void {
                if (array_key_exists('zoom', $arguments)) {
                    $set($targetField, $arguments['zoom']);
                }
            });
    }
}
