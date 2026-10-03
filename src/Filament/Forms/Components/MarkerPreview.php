<?php

namespace CharlesStOlive\FilamentMap\Filament\Forms\Components;

use CharlesStOlive\FilamentMap\Models\GeoPointType;
use CharlesStOlive\FilamentMap\Support\MarkerPreview as Preview;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

/**
 * L'aperçu en direct du marqueur d'un type de point, dans son formulaire : dessiné par le code de la carte
 * (resources/js/layers/marker-element.js), en taille réelle sur fond clair et sombre, puis agrandi avec sa zone de
 * contenu en pointillés et son point d'ancrage. Quand le type accepte une image, des images d'exemple de chaque format
 * montrent comment elle s'y recadre.
 *
 *     MarkerPreview::make()
 *
 * Il lit le style (`marker_style`), l'icône (`icon`) et la couleur (`color`) du formulaire : ces champs doivent être
 * `live()` pour qu'il suive la saisie.
 */
class MarkerPreview extends Component
{
    protected string $view = 'filament-map::forms.components.marker-preview';

    protected string $styleField = 'marker_style';

    protected string $iconField = 'icon';

    protected string $colorField = 'color';

    public static function make(): static
    {
        $static = app(static::class);
        $static->configure();

        return $static;
    }

    /** @return array<string, mixed> */
    public function getPreview(): array
    {
        return $this->evaluate(function (Get $get): array {
            $record = $this->getRecord();
            $collection = config('filament-map.media_collections.default_marker_image', 'default_marker_image');

            return Preview::for(
                (array) ($get($this->styleField) ?? []),
                $get($this->iconField),
                $get($this->colorField),
                $record instanceof GeoPointType ? ($record->getFirstMediaUrl($collection) ?: null) : null,
            );
        });
    }
}
