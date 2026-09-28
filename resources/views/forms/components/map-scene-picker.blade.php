{{--
    MapScenePicker : une carte par scène (vignette, nom, description), chacune un bouton radio de Filament. La carte
    choisie est cerclée de la couleur principale ; une scène sans vignette montre une icône de carte.
--}}
@if ($scenes->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Aucune scène active : créez-en une dans « Scènes cartographiques ».
    </p>
@else
    <div {{ $containerAttributes }}>
        @foreach ($scenes as $scene)
            <label
                for="{{ $id }}-{{ $scene->getKey() }}"
                class="flex cursor-pointer flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 transition has-[:checked]:ring-2 has-[:checked]:ring-primary-600 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-70 dark:bg-gray-900 dark:ring-white/10 dark:has-[:checked]:ring-primary-500"
                data-map-scene-option="{{ $scene->getKey() }}"
            >
                <div class="aspect-video w-full bg-gray-100 dark:bg-white/5">
                    @if (filled($scene->thumbnail))
                        <img src="{{ $scene->thumbnail }}" alt="" loading="lazy" class="h-full w-full object-cover">
                    @else
                        <div class="grid h-full w-full place-items-center text-gray-400 dark:text-gray-500">
                            <x-filament::icon icon="heroicon-o-map" class="h-10 w-10" />
                        </div>
                    @endif
                </div>

                <div class="flex items-start gap-3 p-3">
                    <input type="radio" {{ $inputAttributes($scene)->class(['mt-0.5']) }} />

                    <div class="min-w-0 text-sm">
                        <p class="font-medium text-gray-950 dark:text-white">{{ $scene->name }}</p>

                        @if (filled($scene->description))
                            <p class="mt-0.5 line-clamp-2 text-gray-500 dark:text-gray-400">{{ $scene->description }}</p>
                        @endif
                    </div>
                </div>
            </label>
        @endforeach
    </div>
@endif
