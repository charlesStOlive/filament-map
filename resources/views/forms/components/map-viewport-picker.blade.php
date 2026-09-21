@php
    $mapDomId = 'filament-map-viewport-picker-' . str($getStatePath())->slug('-');
    $statePath = $getStatePath();
    $parentStatePath = str($statePath)->beforeLast('.')->toString();
    $fieldPath = fn (string $field): string => filled($parentStatePath) ? "{$parentStatePath}.{$field}" : $field;
    $mapPayload = $getMapPayload();
@endphp

@assets
    <link rel="stylesheet" href="{{ config('filament-map.maplibre.css_url') }}">
    <script src="{{ config('filament-map.maplibre.js_url') }}"></script>
    <script type="module" src="{{ asset(config('filament-map.maplibre.assets_path', 'vendor/filament-map/filament-map.js')) }}"></script>
    <script src="{{ asset('vendor/filament-map/map-viewport-picker.js') }}"></script>
    <style>
        /* Plein écran : la fenêtre du popup (ou, hors popup, le sélecteur lui-même) prend tout l'écran, la carte tout ce qui reste. */
        .fi-modal-window:fullscreen {
            width: 100vw;
            max-width: none;
            height: 100dvh;
            max-height: none;
            border-radius: 0;
            overflow-y: auto;
        }

        .fi-modal-window:fullscreen [id^='filament-map-viewport-picker-'] {
            height: max(20rem, calc(100dvh - 17rem));
        }

        [data-map-picker]:fullscreen {
            overflow-y: auto;
            padding: 1rem;
            background: white;
        }

        .dark [data-map-picker]:fullscreen {
            background: rgb(3 7 18);
        }

        [data-map-picker]:fullscreen [id^='filament-map-viewport-picker-'] {
            height: max(20rem, calc(100dvh - 9rem));
        }
    </style>
@endassets

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:key="{{ $mapDomId }}-{{ $mapPayload['scene']['id'] ?? $mapPayload['map']['id'] ?? 'empty' }}"
        wire:ignore
        x-data="filamentMapViewportPicker({
            id: @js($mapDomId),
            latitudePath: @js($fieldPath($getLatitudeField())),
            longitudePath: @js($fieldPath($getLongitudeField())),
            zoomPath: @js($fieldPath($getZoomField())),
            boundsPath: @js($fieldPath($getBoundsField())),
            syncBounds: @js($shouldSyncBounds()),
            type: @js($getType()),
            scope: @js('viewport-picker-' . $mapDomId),
            mapPayload: @js($mapPayload),
            defaults: {
                lat: @js(config('filament-map.default.lat', 48.8566)),
                lng: @js(config('filament-map.default.lng', 2.3522)),
                zoom: @js(config('filament-map.default.zoom', 10)),
            },
            tiles: {
                url: @js(config('filament-map.tiles.url')),
                attribution: @js(config('filament-map.tiles.attribution')),
            },
            componentKey: @js($getKey()),
            search: @js($hasSearch()),
        })"
        x-init="init()"
        data-map-picker
        class="space-y-3"
    >
        {{-- La recherche d'adresse (un champ, pas un <form> : le sélecteur peut vivre dans un formulaire) et le plein écran. --}}
        <div class="flex flex-wrap items-center gap-2" data-map-search-bar>
            @if ($hasSearch())
                <div class="min-w-[14rem] flex-1">
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input
                            type="search"
                            x-model="query"
                            x-on:keydown.enter.prevent="search()"
                            placeholder="Chercher une ville, un lieu ou une adresse…"
                            aria-label="Chercher une adresse"
                            autocomplete="off"
                            data-map-search-input
                        />
                    </x-filament::input.wrapper>
                </div>

                <x-filament::button
                    type="button"
                    color="gray"
                    x-on:click="search()"
                    x-bind:disabled="searching || query.trim().length < 3"
                >
                    Rechercher
                </x-filament::button>
            @else
                <div class="flex-1"></div>
            @endif

            <x-filament::icon-button
                icon="heroicon-o-arrows-pointing-out"
                color="gray"
                label="Plein écran"
                tooltip="Plein écran"
                x-show="! fullscreen"
                x-on:click="toggleFullscreen()"
                data-map-fullscreen
            />
            <x-filament::icon-button
                icon="heroicon-o-arrows-pointing-in"
                color="gray"
                label="Quitter le plein écran"
                tooltip="Quitter le plein écran"
                x-show="fullscreen"
                x-cloak
                x-on:click="toggleFullscreen()"
            />
        </div>

        @if ($hasSearch())
            <div x-show="searched" x-cloak class="rounded-lg border border-gray-200 text-sm dark:border-white/10" data-map-search-results>
                <p x-show="message" x-text="message" class="px-3 py-2 text-gray-500 dark:text-gray-400"></p>

                <ul x-show="results.length" class="divide-y divide-gray-100 dark:divide-white/5">
                    <template x-for="(result, index) in results" :key="index">
                        <li>
                            <button
                                type="button"
                                x-on:click="pick(result)"
                                class="block w-full px-3 py-2 text-start text-gray-950 transition hover:bg-gray-50 dark:text-white dark:hover:bg-white/5"
                                x-text="result.label"
                            ></button>
                        </li>
                    </template>
                </ul>
            </div>
        @endif

        <div id="{{ $mapDomId }}" class="{{ $getHeight() }} min-h-[320px] w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"></div>

        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
            @if ($getType() === \CharlesStOlive\FilamentMap\Filament\Forms\Components\MapViewportPicker::TYPE_VIEWPORT)
                <x-filament::button type="button" color="gray" size="xs" x-on:click="syncFromMap()">
                    Utiliser la vue actuelle
                </x-filament::button>

                @if ($shouldSyncBounds())
                    <x-filament::button type="button" color="gray" size="xs" x-on:click="fitConfiguredBounds()">
                        Revenir aux bounds
                    </x-filament::button>
                @endif
            @else
                <x-filament::button type="button" color="gray" size="xs" icon="heroicon-m-viewfinder-circle" x-on:click="centerOnMarker()">
                    Centrer sur le repère
                </x-filament::button>
            @endif

            <span x-text="summary"></span>
        </div>
    </div>
</x-dynamic-component>
