<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use CharlesStOlive\FilamentMap\Models\GeoPointType;
use CharlesStOlive\FilamentMap\Support\MarkerPreview;
use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Le choix d'un type de point : le type choisi (son marqueur, dessiné par le code de la carte, son nom, ce qu'il
 * montre) et un popup qui présente les types actifs en cartes carrées — comme MapScenePicker pour les scènes, deux
 * fois plus petites —, avec une recherche par nom, un filtre par contenu (icône, image, texte, forme seule) et un
 * classement (ordre, nom, taille). L'état est l'identifiant du type.
 *
 *     GeoPointTypePicker::make('point_type_id')->live()
 *
 * `->placeholder('Celui du voyage')` permet de ne rien choisir (une carte de plus, en tête) ; `->placeholderType()` donne
 * le type qu'on montre alors. `->query()` change la requête, `->columns()` le nombre de cartes par ligne du popup.
 */
class GeoPointTypePicker extends Field
{
    protected string $view = 'filament-map::forms.components.geo-point-type-picker';

    protected ?Closure $modifyQueryUsing = null;

    protected string|Closure|null $placeholder = null;

    protected int|Closure|null $placeholderType = null;

    /** @var Collection<int, GeoPointType>|null */
    protected ?Collection $types = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Type de point');
        $this->columns(['default' => 2, 'sm' => 4, 'lg' => 6]);
        $this->rule(fn (GeoPointTypePicker $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            if (filled($value) && ! $component->getTypes()->contains(fn (GeoPointType $type): bool => (string) $type->getKey() === (string) $value)) {
                $fail('Ce type de point n’est pas disponible.');
            }
        });
    }

    /** @param  Closure(Builder): (Builder|null)  $callback */
    public function query(?Closure $callback): static
    {
        $this->modifyQueryUsing = $callback;
        $this->types = null;

        return $this;
    }

    /** Ne rien choisir est permis : ce que le champ dit alors (« Celui du voyage »). */
    public function placeholder(string|Closure|null $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->evaluate($this->placeholder);
    }

    /** Le type qu'on montre quand rien n'est choisi (celui qui s'appliquera alors). */
    public function placeholderType(int|Closure|null $typeId): static
    {
        $this->placeholderType = $typeId;

        return $this;
    }

    public function getPlaceholderType(): ?int
    {
        $id = $this->evaluate($this->placeholderType);

        return filled($id) ? (int) $id : null;
    }

    /** @return Collection<int, GeoPointType> */
    public function getTypes(): Collection
    {
        if ($this->types !== null) {
            return $this->types;
        }

        $query = GeoPointType::query()->with('media')->where('is_active', true)->orderBy('sort_order')->orderBy('name');

        if ($this->modifyQueryUsing !== null) {
            $query = $this->evaluate($this->modifyQueryUsing, ['query' => $query]) ?? $query;
        }

        return $this->types = $query->get();
    }

    /**
     * Chaque type, pour le popup et le résumé : son aperçu (MarkerPreview), et de quoi le filtrer et le classer — ce
     * qu'il montre réellement (`kind` : image s'il en accepte une, sinon icon, text ou none), sa taille en %, son
     * ordre.
     *
     * @return array<int, array{id: int, name: string, kind: string, size: float, order: int, preview: array<string, mixed>}>
     */
    public function getCards(): array
    {
        $collection = config('filament-map.media_collections.default_marker_image', 'default_marker_image');

        return $this->getTypes()->map(function (GeoPointType $type) use ($collection): array {
            $preview = MarkerPreview::for($type->marker_style ?? [], $type->icon, $type->color, $type->getFirstMediaUrl($collection) ?: null);

            return [
                'id' => $type->getKey(),
                'name' => $type->name,
                'kind' => $preview['acceptsImage'] ? 'image' : $preview['appearances']['none']['content']['type'],
                'size' => $preview['size']['percent'],
                'order' => (int) $type->sort_order,
                'preview' => $preview,
            ];
        })->values()->all();
    }

    /** @return array<string, string> Les contenus du filtre. */
    public function getKinds(): array
    {
        return ['icon' => 'Icône', 'image' => 'Image', 'text' => 'Texte', 'none' => 'Forme seule'];
    }
}
