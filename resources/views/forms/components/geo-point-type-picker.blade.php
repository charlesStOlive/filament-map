{{--
    GeoPointTypePicker : une carte carrée par type de point (son marqueur en taille réelle, son nom, s'il accepte une
    image), chacune un bouton radio de Filament. La carte choisie est cerclée de la couleur principale.
--}}
@if ($types->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Aucun type de point actif : créez-en un dans « Types de points ».
    </p>
@else
    <div {{ $containerAttributes }}>
        @foreach ($types as $type)
            @php($typePreview = $preview($type))
            <label
                for="{{ $id }}-{{ $type->getKey() }}"
                class="flex cursor-pointer flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 transition has-[:checked]:ring-2 has-[:checked]:ring-primary-600 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-70 dark:bg-gray-900 dark:ring-white/10 dark:has-[:checked]:ring-primary-500"
                data-geo-point-type-option="{{ $type->getKey() }}"
                title="{{ $typePreview['status']['detail'] }}"
            >
                @include('filament-map::partials.marker-preview', ['preview' => $typePreview, 'variant' => 'tile'])

                <div class="flex items-start gap-2 p-2">
                    <input type="radio" {{ $inputAttributes($type)->class(['mt-0.5']) }} />

                    <div class="min-w-0 text-xs">
                        <p class="truncate font-medium text-gray-950 dark:text-white">{{ $type->name }}</p>

                        @if ($typePreview['acceptsImage'])
                            <p class="text-success-600 dark:text-success-400">Avec image</p>
                        @endif
                    </div>
                </div>
            </label>
        @endforeach
    </div>
@endif
