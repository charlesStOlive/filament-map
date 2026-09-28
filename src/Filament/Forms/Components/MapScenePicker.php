<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use CharlesStOlive\FilamentMap\Models\MapScene;
use Closure;
use Filament\Forms\Components\Radio;
use Filament\Support\Enums\GridDirection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Le choix d'une scène cartographique, en cartes : sa vignette (voir MapSceneResource, « Vue initiale et vignette »), son
 * nom et sa description. Filament n'a pas de bouton radio à image : c'est son Radio — mêmes options, même validation, même
 * état, `->columns()`, `->live()` —, rendu autrement, avec ses propres cases à cocher (`fi-radio-input`).
 *
 *     MapScenePicker::make('map_scene_id')->required()->live()
 *
 * Il propose les scènes actives, par nom ; `->query()` change la requête.
 */
class MapScenePicker extends Radio
{
    protected ?Closure $modifyQueryUsing = null;

    /** @var Collection<int, MapScene>|null */
    protected ?Collection $scenes = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Scène cartographique');
        $this->options(fn (MapScenePicker $component): array => $component->getScenes()->pluck('name', 'id')->all());
        $this->columns(['default' => 1, 'sm' => 2, 'xl' => 3]);
    }

    /** @param  Closure(Builder): (Builder|null)  $callback */
    public function query(?Closure $callback): static
    {
        $this->modifyQueryUsing = $callback;
        $this->scenes = null;

        return $this;
    }

    /** @return Collection<int, MapScene> */
    public function getScenes(): Collection
    {
        if ($this->scenes !== null) {
            return $this->scenes;
        }

        $query = MapScene::query()->where('is_active', true)->orderBy('name');

        if ($this->modifyQueryUsing !== null) {
            $query = $this->evaluate($this->modifyQueryUsing, ['query' => $query]) ?? $query;
        }

        return $this->scenes = $query->get();
    }

    public function toEmbeddedHtml(): string
    {
        $statePath = $this->getStatePath();
        $hasError = $this->hasErrorForPath($statePath);

        $html = view('filament-map::forms.components.map-scene-picker', [
            'id' => $this->getId(),
            'scenes' => $this->getScenes(),
            'containerAttributes' => $this->getExtraAttributeBag()
                ->grid($this->getColumns(), $this->getGridDirection() ?? GridDirection::Row)
                ->merge(['aria-labelledby' => "{$this->getId()}-label", 'role' => 'radiogroup'], escape: false)
                ->class(['fi-fo-map-scene-picker gap-4']),
            'inputAttributes' => fn (MapScene $scene) => $this->getExtraInputAttributeBag()
                ->merge([
                    'disabled' => $this->isDisabled() || $this->isOptionDisabled($scene->getKey(), $scene->name),
                    'id' => "{$this->getId()}-{$scene->getKey()}",
                    'name' => $this->getId(),
                    'value' => $scene->getKey(),
                    $this->applyStateBindingModifiers('wire:model') => $statePath,
                ], escape: false)
                ->class(['fi-radio-input', 'fi-valid' => ! $hasError, 'fi-invalid' => $hasError]),
        ])->render();

        return $this->wrapEmbeddedHtml($html, labelTag: 'div');
    }
}
