<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use CharlesStOlive\FilamentMap\Models\GeoPointType;
use CharlesStOlive\FilamentMap\Support\MarkerPreview;
use Closure;
use Filament\Forms\Components\Radio;
use Filament\Support\Enums\GridDirection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Le choix d'un type de point, en cartes carrées — comme MapScenePicker pour les scènes, deux fois plus petites : son
 * marqueur en taille réelle (dessiné par le code de la carte, voir partials/marker-preview), son nom, et s'il accepte
 * une image. C'est le Radio de Filament — mêmes options, même validation, même état, `->columns()`, `->live()` —,
 * rendu autrement.
 *
 *     GeoPointTypePicker::make('point_type_id')->live()
 *
 * Il propose les types actifs, par ordre puis par nom ; `->query()` change la requête.
 */
class GeoPointTypePicker extends Radio
{
    protected ?Closure $modifyQueryUsing = null;

    /** @var Collection<int, GeoPointType>|null */
    protected ?Collection $types = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Type de point');
        $this->options(fn (GeoPointTypePicker $component): array => $component->getTypes()->pluck('name', 'id')->all());
        $this->columns(['default' => 2, 'sm' => 4, 'xl' => 6]);
    }

    /** @param  Closure(Builder): (Builder|null)  $callback */
    public function query(?Closure $callback): static
    {
        $this->modifyQueryUsing = $callback;
        $this->types = null;

        return $this;
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

    public function toEmbeddedHtml(): string
    {
        $statePath = $this->getStatePath();
        $hasError = $this->hasErrorForPath($statePath);
        $collection = config('filament-map.media_collections.default_marker_image', 'default_marker_image');

        $html = view('filament-map::forms.components.geo-point-type-picker', [
            'id' => $this->getId(),
            'types' => $this->getTypes(),
            'preview' => fn (GeoPointType $type): array => MarkerPreview::for(
                $type->marker_style ?? [],
                $type->icon,
                $type->color,
                $type->getFirstMediaUrl($collection) ?: null,
            ),
            'containerAttributes' => $this->getExtraAttributeBag()
                ->grid($this->getColumns(), $this->getGridDirection() ?? GridDirection::Row)
                ->merge(['aria-labelledby' => "{$this->getId()}-label", 'role' => 'radiogroup'], escape: false)
                ->class(['fi-fo-geo-point-type-picker gap-3']),
            'inputAttributes' => fn (GeoPointType $type) => $this->getExtraInputAttributeBag()
                ->merge([
                    'disabled' => $this->isDisabled() || $this->isOptionDisabled($type->getKey(), $type->name),
                    'id' => "{$this->getId()}-{$type->getKey()}",
                    'name' => $this->getId(),
                    'value' => $type->getKey(),
                    $this->applyStateBindingModifiers('wire:model') => $statePath,
                ], escape: false)
                ->class(['fi-radio-input', 'fi-valid' => ! $hasError, 'fi-invalid' => $hasError]),
        ])->render();

        return $this->wrapEmbeddedHtml($html, labelTag: 'div');
    }
}
