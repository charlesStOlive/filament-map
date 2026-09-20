{{--
    Le bouton (une icône, le libellé est une infobulle) et le popup qu'il ouvre :
    ils partagent une seule vue pour ne prendre qu'un emplacement dans la ligne.

    `:x-on:click` (expression PHP) et non `x-on:click` : Blade ne compile pas
    `@js()` dans un attribut de balise <x-…>. L'identifiant est un slug, sans
    guillemet à échapper.
--}}
<x-filament::icon-button
    icon="heroicon-o-map-pin"
    color="gray"
    label="Choisir sur la carte"
    tooltip="Choisir sur la carte"
    :x-on:click="'$dispatch(\'open-modal\', { id: \'' . $modalId . '\' })'"
/>

<x-filament::modal :id="$modalId" width="4xl" teleport="body">
    <x-slot name="heading">Choisir sur la carte</x-slot>

    {{ $getChildSchema() }}

    <x-slot name="footer">
        <x-filament::button type="button" :x-on:click="'$dispatch(\'close-modal\', { id: \'' . $modalId . '\' })'">
            Terminé
        </x-filament::button>
    </x-slot>
</x-filament::modal>
