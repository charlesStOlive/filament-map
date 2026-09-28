<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use CharlesStOlive\FilamentMap\Models\MapScene;
use CharlesStOlive\FilamentMap\Services\Geocoding\Geocoder;
use CharlesStOlive\FilamentMap\Services\Geocoding\GeocodingException;
use CharlesStOlive\FilamentMap\Services\MapPayloadBuilder;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

/**
 * La moitié « affichage » de MapPositionInput : le résumé mis en forme dans la page, et le popup qui porte le formulaire
 * (position, zoom…) et la carte. Elle ne stocke rien elle-même : les valeurs sont celles des champs frères que
 * MapPositionInput crée, et que le popup n'écrit qu'à « Valider ».
 *
 * Elle porte aussi la recherche d'adresse, que la carte appelle depuis le navigateur.
 */
class MapPositionEditor extends Field
{
    protected string $view = 'filament-map::forms.components.map-position-editor';

    protected string $latitudeField = 'latitude';

    protected string $longitudeField = 'longitude';

    /** @var array<string, array{label: string, kind: string, min: float|null, max: float|null, step: float, follow: bool, role: string|null}> */
    protected array $fields = [];

    protected ?string $thumbnailField = null;

    /** @var array{width: int, height: int, marker: bool} */
    protected array $thumbnailFormat = ['width' => 192, 'height' => 192, 'marker' => true];

    protected bool|Closure $isPositionRequired = false;

    protected bool $hasSearch = true;

    protected MapScene|int|string|Closure|null $scene = null;

    protected bool $hasSceneConstraint = false;

    protected int|Closure|null $initialZoom = null;

    protected string $mapHeight = 'h-[65vh]';

    /**
     * @param  array<string, array{label: string, kind: string, min: float|null, max: float|null, step: float, follow: bool, role: string|null}>  $fields
     * @param  array{width: int, height: int, marker: bool}  $thumbnailFormat
     */
    public function configureFor(
        string $latitudeField,
        string $longitudeField,
        array $fields,
        ?string $thumbnailField,
        array $thumbnailFormat,
        bool|Closure $required,
        bool $search,
        MapScene|int|string|Closure|null $scene,
        bool $hasSceneConstraint,
        int|Closure|null $initialZoom,
        string $mapHeight,
    ): static {
        $this->latitudeField = $latitudeField;
        $this->longitudeField = $longitudeField;
        $this->fields = $fields;
        $this->thumbnailField = $thumbnailField;
        $this->thumbnailFormat = $thumbnailFormat;
        // Évalué à l'affichage : la fermeture a besoin du formulaire (`Get`), où ce champ n'est pas encore rattaché ici.
        $this->isPositionRequired = $required;
        $this->hasSearch = $search;
        $this->scene = $scene;
        $this->hasSceneConstraint = $hasSceneConstraint;
        $this->initialZoom = $initialZoom;
        $this->mapHeight = $mapHeight;

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

    /** @return array<string, array{label: string, kind: string, min: float|null, max: float|null, step: float, follow: bool, role: string|null}> */
    public function getPositionFields(): array
    {
        return $this->fields;
    }

    public function getThumbnailField(): ?string
    {
        return $this->thumbnailField;
    }

    /** @return array{width: int, height: int, marker: bool} */
    public function getThumbnailFormat(): array
    {
        return $this->thumbnailFormat;
    }

    public function isPositionRequired(): bool
    {
        return (bool) $this->evaluate($this->isPositionRequired);
    }

    public function getMapHeight(): string
    {
        return $this->mapHeight;
    }

    /** La recherche d'adresse cale la carte sur le lieu : sans carte (aucune scène connue), il n'y en a pas. */
    public function hasSearch(): bool
    {
        return $this->hasSearch && $this->isMapAvailable() && (bool) config('filament-map.geocoding.enabled', true);
    }

    public function resolveScene(): ?MapScene
    {
        $scene = $this->evaluate($this->scene);

        if ($scene instanceof MapScene) {
            return $scene;
        }

        return filled($scene) ? MapScene::query()->find($scene) : null;
    }

    /** Sans scène connue (quand une scène est exigée), il n'y a pas de carte où pointer : on ne montre que le résumé. */
    public function isMapAvailable(): bool
    {
        return ! $this->hasSceneConstraint || $this->resolveScene() !== null;
    }

    public function getMapPayload(): ?array
    {
        $scene = $this->resolveScene();

        if (! $scene) {
            return null;
        }

        $overrides = ['points' => []];
        $zoom = $this->evaluate($this->initialZoom);

        if ($zoom !== null) {
            $overrides['map'] = ['zoom' => $zoom];
        }

        return app(MapPayloadBuilder::class)->build($scene, $overrides);
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
}
