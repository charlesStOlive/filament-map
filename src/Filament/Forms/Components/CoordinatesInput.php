<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use CharlesStOlive\FilamentMap\Models\MapScene;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Concerns\HasLabel;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\View;

/**
 * Une position (latitude + longitude) saisie à la main ou choisie sur une
 * carte, en un seul composant : une seule ligne (deux champs numériques
 * bornés fusionnés, la règle « les deux ou aucune »), et un unique bouton
 * icône qui ouvre la carte dans un popup.
 *
 * La position se stocke de deux manières, selon le nom donné à `make()` :
 *
 *  - dans un JSON : `CoordinatesInput::make('position')` — l'état est
 *    `['latitude' => …, 'longitude' => …]` sous la clé `position` (colonne
 *    cast en array, ou clé d'un tableau JSON) ;
 *  - dans deux colonnes SQL : `CoordinatesInput::make()` — les deux champs
 *    sont des frères du composant, nommés `latitude` / `longitude` ou par
 *    `->latitudeField()` / `->longitudeField()`.
 *
 * Dans les deux cas `latitudeField()` / `longitudeField()` nomment les deux
 * valeurs : ce sont les clés du JSON ou les noms des colonnes.
 */
class CoordinatesInput extends Component
{
    use HasLabel;

    protected string $view = 'filament-map::forms.components.coordinates-input';

    protected string $latitudeField = 'latitude';

    protected string $longitudeField = 'longitude';

    protected ?string $zoomField = null;

    protected bool | Closure $isRequired = false;

    protected bool $hasMap = true;

    // Le popup est large : la carte prend l'essentiel de la hauteur de l'écran (et tout l'écran en plein écran).
    protected string $mapHeight = 'h-[65vh]';

    protected MapScene | int | string | Closure | null $scene = null;

    protected bool $hasSceneConstraint = false;

    protected int | Closure | null $initialZoom = null;

    final public function __construct(?string $name = null)
    {
        $this->statePath($name);
    }

    public static function make(?string $name = null): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->columnSpanFull();
        $this->label('Coordonnées');

        $this->schema(fn (): array => $this->buildSchema());
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

    /**
     * Fait de la carte un sélecteur de cadrage : la position est celle du repère (posé au clic sur la carte, ou déplacé),
     * et le zoom celui que la carte affiche, arrondi (le champ de zoom reste au frère de ce composant, il n'en fait pas
     * partie). Déplacer la carte ne change pas la position : le bouton « Centrer sur le repère » y ramène la vue.
     */
    public function viewport(string $zoomField): static
    {
        $this->zoomField = $zoomField;

        return $this;
    }

    public function required(bool | Closure $condition = true): static
    {
        $this->isRequired = $condition;

        return $this;
    }

    public function isRequired(): bool
    {
        return (bool) $this->evaluate($this->isRequired);
    }

    /**
     * Scène dont les couches habillent la carte. Une fois appelée, la carte
     * (et son bouton) n'existe que tant que la scène est connue : un voyage
     * sans scène choisie n'a pas de carte où pointer.
     */
    public function scene(MapScene | int | string | Closure | null $scene): static
    {
        $this->scene = $scene;
        $this->hasSceneConstraint = true;

        return $this;
    }

    /** Zoom de départ de la carte, sans toucher au zoom propre de la scène. */
    public function initialZoom(int | Closure | null $zoom): static
    {
        $this->initialZoom = $zoom;

        return $this;
    }

    public function mapHeight(string $height): static
    {
        $this->mapHeight = $height;

        return $this;
    }

    /** Saisie seule : ni bouton, ni carte. */
    public function withoutMap(bool $condition = true): static
    {
        $this->hasMap = ! $condition;

        return $this;
    }

    public function isMapAvailable(): bool
    {
        if (! $this->hasMap) {
            return false;
        }

        return ! $this->hasSceneConstraint || $this->resolveScene() !== null;
    }

    public function resolveScene(): ?MapScene
    {
        $scene = $this->evaluate($this->scene);

        if ($scene instanceof MapScene) {
            return $scene;
        }

        return filled($scene) ? MapScene::query()->find($scene) : null;
    }

    /** @return array<Component> */
    protected function buildSchema(): array
    {
        $group = FusedGroup::make([
            $this->numberInput($this->latitudeField, 'Latitude', 'Lat', 90, $this->longitudeField),
            $this->numberInput($this->longitudeField, 'Longitude', 'Lng', 180, $this->latitudeField),
        ])
            ->label(fn (): mixed => $this->getLabel())
            ->hiddenLabel(fn (): bool => $this->isLabelHidden())
            ->columns(2)
            ->markAsRequired(fn (): bool => $this->isRequired());

        if (! $this->hasMap) {
            return [$group];
        }

        // Le bouton est dans le prolongement de la ligne : les deux champs
        // et lui tiennent dans l'emplacement d'un champ ordinaire. Le popup
        // (téléporté dans le body) vit dans la même vue, sans rangée à lui.
        return [
            $group->afterContent([
                View::make('filament-map::forms.components.coordinates-map')
                    ->viewData(['modalId' => $this->modalId()])
                    ->schema([$this->picker()])
                    ->visible(fn (): bool => $this->isMapAvailable()),
            ]),
        ];
    }

    /** Identifiant du popup : unique par instance, y compris dans un répéteur. */
    protected function modalId(): string
    {
        $path = ($this->getContainer()->getStatePath() ?? '').'.'.($this->getStatePath(false) ?? $this->latitudeField);

        return 'filament-map-coordinates-'.str($path)->replace('.', '-')->slug('-');
    }

    protected function numberInput(string $name, string $label, string $prefix, int $limit, string $partner): TextInput
    {
        $input = TextInput::make($name)
            ->label($label)
            ->hiddenLabel()
            ->prefix($prefix)
            ->extraInputAttributes(['title' => $label])
            ->numeric()
            ->step('0.0000001')
            ->minValue(-$limit)
            ->maxValue($limit)
            ->required(fn (): bool => $this->isRequired())
            ->requiredWith($partner);

        // `->live()` posé sur le composant vaut pour ses deux champs.
        return $this->isLive()
            ? $input->live(onBlur: $this->isLiveOnBlur(), debounce: $this->getLiveDebounce())
            : $input;
    }

    protected function picker(): MapViewportPicker
    {
        $picker = MapViewportPicker::make('coordinate_picker')
            ->hiddenLabel()
            ->dehydrated(false)
            ->latitudeField($this->latitudeField)
            ->longitudeField($this->longitudeField)
            ->height($this->mapHeight)
            ->scene(fn (): ?MapScene => $this->resolveScene())
            ->initialZoom(fn (): ?int => $this->evaluate($this->initialZoom));

        return $this->zoomField === null
            ? $picker->coordinate()
            : $picker->markerAndZoom()->zoomField($this->zoomField);
    }
}
