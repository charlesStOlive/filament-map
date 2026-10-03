<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use CharlesStOlive\FilamentMap\Support\IconCatalog;
use CharlesStOlive\FilamentMap\Support\MarkerSvg;
use Closure;
use Filament\Forms\Components\Field;

/**
 * Le choix d'une icône : l'icône choisie et son nom, et un popup qui parcourt le catalogue (IconCatalog : les jeux
 * Blade Icons de l'application, Heroicons rangé par variante) — recherche par nom, filtre par jeu. L'état est le nom de
 * l'icône (`heroicon-o-map-pin`), comme un champ texte.
 *
 *     IconPicker::make('icon')->live()
 *
 * `->clearable(false)` retire le bouton « Retirer ». Le catalogue est servi par la route `filament-map.icons`.
 */
class IconPicker extends Field
{
    protected string $view = 'filament-map::forms.components.icon-picker';

    protected bool|Closure $isClearable = true;

    protected string|Closure|null $emptyLabel = 'Aucune icône';

    public function clearable(bool|Closure $condition = true): static
    {
        $this->isClearable = $condition;

        return $this;
    }

    public function isClearable(): bool
    {
        return (bool) $this->evaluate($this->isClearable);
    }

    /** Ce que le champ dit quand aucune icône n'est choisie (par exemple « Celle du type »). */
    public function emptyLabel(string|Closure|null $label): static
    {
        $this->emptyLabel = $label;

        return $this;
    }

    public function getEmptyLabel(): ?string
    {
        return $this->evaluate($this->emptyLabel);
    }

    /** Le dessin de l'icône choisie : celui du catalogue, sinon celui de Blade Icons (un jeu écarté du catalogue). */
    public function getStateSvg(): ?string
    {
        $name = $this->getState();

        return is_string($name) && filled($name) ? (app(IconCatalog::class)->svg($name) ?? MarkerSvg::icon($name)) : null;
    }
}
