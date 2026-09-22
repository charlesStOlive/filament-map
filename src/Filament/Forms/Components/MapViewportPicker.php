<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use CharlesStOlive\FilamentMap\Models\MapScene;
use CharlesStOlive\FilamentMap\Services\Geocoding\Geocoder;
use CharlesStOlive\FilamentMap\Services\Geocoding\GeocodingException;
use CharlesStOlive\FilamentMap\Services\MapPayloadBuilder;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Support\Js;
use Livewire\Attributes\Renderless;

/**
 * @deprecated Utiliser MapPositionInput (celui-ci reporte ses changements en direct dans le formulaire).
 */
class MapViewportPicker extends Field
{
    public const TYPE_COORDINATE = 'coordinate';

    public const TYPE_VIEWPORT = 'viewport';

    /**
     * Un repère posé au clic (ou déplacé) et le zoom courant de la carte : la carte se déplace librement sans toucher au
     * repère, seul le zoom suit. C'est le cadrage d'une vue de départ : « centrée sur ce point, à ce zoom ».
     */
    public const TYPE_MARKER_ZOOM = 'marker-zoom';

    protected string $view = 'filament-map::forms.components.map-viewport-picker';

    protected MapScene|int|string|Closure|null $scene = null;

    protected int|Closure|null $initialZoom = null;

    public function scene(MapScene|int|string|Closure|null $scene): static
    {
        $this->scene = $scene;

        return $this;
    }

    /**
     * Surcharge ponctuelle du zoom de départ affiché par la vue interactive
     * (par ex. un peu plus dézoomé qu'à l'accoutumée pour situer un nouveau
     * point par rapport au précédent), sans toucher au zoom propre de la
     * scène.
     */
    public function initialZoom(int|Closure|null $zoom): static
    {
        $this->initialZoom = $zoom;

        return $this;
    }

    protected string $type = self::TYPE_VIEWPORT;

    protected string $latitudeField = 'center_latitude';

    protected string $longitudeField = 'center_longitude';

    protected string $zoomField = 'zoom';

    protected string $boundsField = 'bounds';

    protected string $height = 'h-[420px]';

    protected bool $syncBounds = true;

    protected bool $hasSearch = true;

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function markerAndZoom(): static
    {
        return $this->type(self::TYPE_MARKER_ZOOM)->syncBounds(false);
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

    /** Sans la recherche d'adresse (elle est proposée par défaut, tant que `filament-map.geocoding.enabled` le permet). */
    public function withoutSearch(bool $condition = true): static
    {
        $this->hasSearch = ! $condition;

        return $this;
    }

    public function hasSearch(): bool
    {
        return $this->hasSearch && (bool) config('filament-map.geocoding.enabled', true);
    }

    /**
     * Cherche un lieu par son nom ou son adresse (voir Services\Geocoding). Appelée par la carte, depuis le navigateur
     * (`$wire.callSchemaComponentMethod`), sans rafraîchir le formulaire.
     *
     * @return array{results: array<int, array{label: string, lat: float, lng: float, bounds: array<string, float>|null}>, error?: string}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function searchAddress(string $query): array
    {
        $query = trim($query);

        if (! $this->hasSearch() || mb_strlen($query) < 3) {
            return ['results' => []];
        }

        try {
            $results = app(Geocoder::class)->search(mb_substr($query, 0, 200));
        } catch (GeocodingException $exception) {
            return ['results' => [], 'error' => $exception->getMessage()];
        }

        return ['results' => array_map(static fn ($result): array => $result->toArray(), $results)];
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

        $zoom = $this->evaluate($this->initialZoom);

        if ($zoom !== null) {
            $overrides['map'] = ['zoom' => $zoom];
        }

        return app(MapPayloadBuilder::class)->build($scene, $overrides);
    }

    /**
     * Bouton à poser en `->suffixAction()` d'un champ de zoom : récupère le
     * zoom actuel de la vue interactive la plus proche (même section) et le
     * pose dans le champ ciblé, sans aller-retour serveur pour lire la carte.
     *
     * Le champ ciblé est retrouvé via `$component` (le champ qui porte cette
     * action) plutôt que par son seul nom : un `Set` non absolu se résout
     * relativement au conteneur de ce même champ, ce qui échoue silencieusement
     * dès que le champ vit dans le mini-formulaire d'une autre Action (une
     * modale imbriquée, par exemple) plutôt qu'à la racine du formulaire.
     */
    public static function captureZoomAction(string $targetField, string $label = 'Utiliser le zoom actuel'): Action
    {
        $actionName = 'capture-zoom-'.$targetField;

        // Le bouton court-circuite le clic Livewire par défaut pour lire le
        // zoom courant de la carte côté client avant de monter l'action ; il
        // doit donc reconstituer lui-même le `context` (dont `schemaComponent`)
        // que Filament ajoute normalement tout seul, sinon l'action est
        // introuvable dès qu'elle vit dans le mini-formulaire d'une autre
        // Action (une modale imbriquée, par exemple).
        //
        // Passer par alpineClickHandler() et non par extraAttributes() :
        // Filament pose déjà `x-on:click => null` dans les attributs du
        // bouton, et cette clé vide l'emporte sur un `x-on:click` d'extraAttributes
        // (le bouton était alors rendu sans aucun gestionnaire de clic).
        return Action::make($actionName)
            ->label($label)
            ->tooltip($label)
            ->icon('heroicon-o-viewfinder-circle')
            ->alpineClickHandler(function (Action $action) use ($actionName): string {
                $context = Js::from($action->getContext());

                // La carte à lire est la plus proche qui englobe le bouton :
                // on remonte les ancêtres jusqu'à en trouver une. Ce script vit
                // dans un attribut HTML : pas de guillemets doubles, Blade les
                // rend `\"` et l'attribut se referme au premier.
                return <<<JS
                    let root = \$el.parentElement;
                    let mapEl = null;
                    while (root && !(mapEl = root.querySelector('[id^=filament-map-viewport-picker-]'))) { root = root.parentElement; }
                    const zoom = mapEl ? window.FilamentMap?.instances?.get(mapEl.id)?.map?.getZoom() : null;
                    if (typeof zoom === 'number') { \$wire.mountAction('{$actionName}', { zoom: Math.round(zoom * 100) / 100 }, {$context}); }
                    JS;
            })
            ->action(function (array $arguments, Set $set, ?Component $component): void {
                if (! array_key_exists('zoom', $arguments) || ! $component) {
                    return;
                }

                $set($component->getStatePath(), $arguments['zoom'], isAbsolute: true);
            });
    }
}
