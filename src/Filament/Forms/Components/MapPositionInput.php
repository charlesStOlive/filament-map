<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use CharlesStOlive\FilamentMap\Models\MapScene;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Concerns\HasLabel;

/**
 * Une position sur la carte, avec les réglages qui l'accompagnent (zoom, zoom minimum et maximum…), en un seul composant.
 *
 * Dans le formulaire de la page, on ne voit qu'un **résumé mis en forme** (latitude, longitude, zoom…) et un bouton pour le
 * modifier. Le **formulaire vit dans un grand popup** : la position se choisit en déplaçant le repère (ou en cliquant sur la
 * carte), en saisissant ses propres coordonnées, ou en cherchant une adresse ; le zoom, le zoom minimum et le zoom
 * maximum se prennent sur la carte avec leur bouton. Rien n'est reporté dans le formulaire avant « Valider » : la croix,
 * Échap et « Annuler » abandonnent les changements, et le popup se rouvre sur les valeurs enregistrées.
 *
 * Les valeurs restent dans des champs frères du composant (colonnes SQL, ou clés d'un tableau JSON), comme avec
 * CoordinatesInput :
 *
 *     MapPositionInput::make()
 *         ->latitudeField('map_center_latitude')->longitudeField('map_center_longitude')
 *         ->zoomField('map_zoom')->minZoomField('map_min_zoom')->maxZoomField('map_max_zoom')
 *         ->scene(fn (Get $get) => MapScene::find($get('map_scene_id')))
 *
 * `->field()` ajoute d'autres réglages numériques, à saisir ou à prendre sur la carte.
 */
class MapPositionInput extends Component
{
    use HasLabel;

    protected string $view = 'filament-map::forms.components.map-position-input';

    protected string $latitudeField = 'latitude';

    protected string $longitudeField = 'longitude';

    /** @var array<string, array{label: string, kind: string, min: float|null, max: float|null, step: float, follow: bool, role: string|null}> */
    protected array $fields = [];

    protected ?string $thumbnailField = null;

    protected bool|Closure $isRequired = false;

    protected bool $hasSearch = true;

    protected MapScene|int|string|Closure|null $scene = null;

    protected bool $hasSceneConstraint = false;

    protected int|Closure|null $initialZoom = null;

    protected string $mapHeight = 'h-[65vh]';

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
        $this->label('Position');

        // Le schéma se construit sur l'instance rattachée au formulaire (`$component`), pas sur celle d'origine : les fermetures
        // qu'il crée (libellé, « requis »…) s'évaluent avec le formulaire (`Get`).
        $this->schema(fn (MapPositionInput $component): array => $component->buildSchema());
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

    /** Le zoom de départ : le champ suit le zoom de la carte quand on la zoome, et se reprend aussi par son bouton. */
    public function zoomField(string $field = 'zoom', string $label = 'Zoom'): static
    {
        return $this->field($field, $label, min: 0, max: 22, step: 0.01, kind: 'zoom', follow: true, role: 'zoom');
    }

    public function minZoomField(string $field = 'min_zoom', string $label = 'Zoom minimum'): static
    {
        return $this->field($field, $label, min: 0, max: 22, step: 0.01, kind: 'zoom', role: 'min');
    }

    public function maxZoomField(string $field = 'max_zoom', string $label = 'Zoom maximum'): static
    {
        return $this->field($field, $label, min: 0, max: 22, step: 0.01, kind: 'zoom', role: 'max');
    }

    /**
     * Un autre réglage numérique du popup. `kind: 'zoom'` lui donne un bouton qui y reporte le zoom actuel de la carte ;
     * `follow: true` fait suivre le champ au zoom de la carte quand on la zoome. `role` (`zoom`, `min`, `max`) dit ce que le
     * champ représente : « Valider » vérifie que le zoom minimum ≤ le zoom de départ ≤ le zoom maximum.
     */
    public function field(string $field, string $label, ?float $min = null, ?float $max = null, float $step = 1, string $kind = 'number', bool $follow = false, ?string $role = null): static
    {
        $this->fields[$field] = ['label' => $label, 'kind' => $kind, 'min' => $min, 'max' => $max, 'step' => $step, 'follow' => $follow, 'role' => $role];

        return $this;
    }

    /** Le champ où garder la miniature carrée de la carte (une image, en texte : voir l'aperçu sous le bouton). */
    public function thumbnailField(?string $field = 'thumbnail'): static
    {
        $this->thumbnailField = $field;

        return $this;
    }

    public function required(bool|Closure $condition = true): static
    {
        $this->isRequired = $condition;

        return $this;
    }

    public function isRequired(): bool
    {
        return (bool) $this->evaluate($this->isRequired);
    }

    /** Scène dont les couches habillent la carte. Une fois appelée, sans scène connue il n'y a pas de carte où pointer. */
    public function scene(MapScene|int|string|Closure|null $scene): static
    {
        $this->scene = $scene;
        $this->hasSceneConstraint = true;

        return $this;
    }

    /** Zoom de départ de la carte, sans toucher au zoom propre de la scène. */
    public function initialZoom(int|Closure|null $zoom): static
    {
        $this->initialZoom = $zoom;

        return $this;
    }

    public function mapHeight(string $height): static
    {
        $this->mapHeight = $height;

        return $this;
    }

    public function withoutSearch(bool $condition = true): static
    {
        $this->hasSearch = ! $condition;

        return $this;
    }

    /** @return array<Component> */
    public function buildSchema(): array
    {
        $required = fn (): bool => $this->isRequired();

        $schema = [
            Hidden::make($this->latitudeField)
                ->rules(['nullable', 'numeric', 'between:-90,90'])
                ->required($required)
                ->requiredWith($this->longitudeField),
            Hidden::make($this->longitudeField)
                ->rules(['nullable', 'numeric', 'between:-180,180'])
                ->required($required)
                ->requiredWith($this->latitudeField),
        ];

        foreach ($this->fields as $name => $field) {
            $rules = ['nullable', 'numeric'];

            if ($field['min'] !== null && $field['max'] !== null) {
                $rules[] = 'between:'.$field['min'].','.$field['max'];
            }

            $schema[] = Hidden::make($name)->rules($rules);
        }

        if ($this->thumbnailField !== null) {
            $schema[] = Hidden::make($this->thumbnailField)->rules(['nullable', 'string', 'max:60000']);
        }

        $schema[] = MapPositionEditor::make('position_'.$this->latitudeField)
            ->label(fn (): mixed => $this->getLabel())
            ->dehydrated(false)
            ->configureFor(
                latitudeField: $this->latitudeField,
                longitudeField: $this->longitudeField,
                fields: $this->fields,
                thumbnailField: $this->thumbnailField,
                required: $this->isRequired,
                search: $this->hasSearch,
                scene: $this->scene,
                hasSceneConstraint: $this->hasSceneConstraint,
                initialZoom: $this->initialZoom,
                mapHeight: $this->mapHeight,
            );

        return $schema;
    }
}
