{{--
    Le résumé d'une position (latitude, longitude, zoom…) dans la page, et le popup qui la modifie : voir MapPositionInput.

    Les valeurs affichées sont celles des champs frères, liées à Livewire par `$wire.$entangle` (le résumé se met à jour à
    « Valider »). Le popup travaille sur un brouillon Alpine (resources/js/map-position-input.js) : rien n'est reporté avant.
--}}
@php
    $statePath = $getStatePath();
    $parentStatePath = str_contains($statePath, '.') ? str($statePath)->beforeLast('.')->toString() : '';
    $fieldPath = fn (string $field): string => filled($parentStatePath) ? "{$parentStatePath}.{$field}" : $field;
    $slug = str($statePath)->slug('-')->toString();
    $mapDomId = 'filament-map-position-map-'.$slug;
    $modalId = 'filament-map-position-modal-'.$slug;
    $positionFields = $getPositionFields();
    $thumbnailField = $getThumbnailField();
    $hasMap = $isMapAvailable();
    $mapPayload = $hasMap ? $getMapPayload() : null;
    $watchedPaths = array_merge(
        [$fieldPath($getLatitudeField()), $fieldPath($getLongitudeField())],
        array_map($fieldPath, array_keys($positionFields)),
    );
@endphp

@assets
    <link rel="stylesheet" href="{{ config('filament-map.maplibre.css_url') }}">
    <script src="{{ config('filament-map.maplibre.js_url') }}"></script>
    <script type="module" src="{{ asset(config('filament-map.maplibre.assets_path', 'vendor/filament-map/filament-map.js')) }}"></script>
    <script src="{{ asset('vendor/filament-map/map-position-input.js') }}"></script>
    <style>
        /* Plein écran : la fenêtre du popup prend tout l'écran, la carte tout ce qui reste. */
        .fi-modal-window:fullscreen {
            width: 100vw;
            max-width: none;
            height: 100dvh;
            max-height: none;
            border-radius: 0;
            overflow-y: auto;
        }

        .fi-modal-window:fullscreen [id^='filament-map-position-map-'] {
            height: max(20rem, calc(100dvh - 12rem));
        }
    </style>
@endassets

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:key="{{ $mapDomId }}-{{ $mapPayload['scene']['id'] ?? $mapPayload['map']['id'] ?? 'empty' }}"
        x-data="filamentMapPosition({
            id: @js($mapDomId),
            modalId: @js($modalId),
            scope: @js('map-position-'.$mapDomId),
            componentKey: @js($getKey()),
            search: @js($hasSearch()),
            hasMap: @js($hasMap),
            required: @js($isPositionRequired()),
            hasThumbnail: @js($thumbnailField !== null),
            fields: @js(collect($positionFields)->map(fn (array $field, string $key): array => ['key' => $key, ...$field])->values()->all()),
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
            values: {
                lat: $wire.$entangle(@js($fieldPath($getLatitudeField()))),
                lng: $wire.$entangle(@js($fieldPath($getLongitudeField()))),
                @if ($thumbnailField !== null)
                    thumbnail: $wire.$entangle(@js($fieldPath($thumbnailField))),
                @endif
                fields: {
                    @foreach ($positionFields as $key => $positionField)
                        @js($key): $wire.$entangle(@js($fieldPath($key))),
                    @endforeach
                },
            },
        })"
        data-map-position-root
    >
        {{-- Le résumé : ce qui est enregistré, mis en forme, avec un aperçu carré de la carte et le bouton pour le modifier. --}}
        <div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-900" data-map-position-summary>
            @if ($thumbnailField !== null)
                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10" data-map-position-thumbnail>
                    <template x-if="values.thumbnail">
                        <img x-bind:src="values.thumbnail" alt="Aperçu de la carte" class="h-full w-full object-cover">
                    </template>
                    <div x-show="! values.thumbnail" class="grid h-full w-full place-items-center text-gray-400 dark:text-gray-500">
                        <x-filament::icon icon="heroicon-o-map" class="h-8 w-8" />
                    </div>
                </div>
            @endif

            <dl class="grid min-w-0 flex-1 grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Latitude</dt>
                    <dd class="font-medium tabular-nums text-gray-950 dark:text-white" x-text="format(values.lat) || '—'"></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Longitude</dt>
                    <dd class="font-medium tabular-nums text-gray-950 dark:text-white" x-text="format(values.lng) || '—'"></dd>
                </div>
                @foreach ($positionFields as $key => $positionField)
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $positionField['label'] }}</dt>
                        <dd class="font-medium tabular-nums text-gray-950 dark:text-white" x-text="format(values.fields[@js($key)], 2) || '—'"></dd>
                    </div>
                @endforeach
            </dl>

            <x-filament::icon-button
                icon="heroicon-o-pencil-square"
                color="gray"
                size="lg"
                label="Modifier la position"
                tooltip="Modifier la position"
                x-on:click="openEditor()"
                data-map-position-edit
            />
        </div>

        @if (isset($errors))
            @foreach ($watchedPaths as $watchedPath)
                @if ($errors->has($watchedPath))
                    <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $errors->first($watchedPath) }}</p>
                @endif
            @endforeach
        @endif

        {{-- Le popup : le formulaire (brouillon) et la carte. Fermé par la croix, Échap ou « Annuler », il oublie ses changements. --}}
        <x-filament::modal :id="$modalId" width="7xl" teleport="body" :close-by-clicking-away="false">
            <x-slot name="heading">{{ $getLabel() }}</x-slot>

            <div class="grid gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]" data-map-position-editor>
                <div class="space-y-4">
                    @if ($hasSearch())
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-gray-950 dark:text-white">Chercher une adresse</label>
                            <div class="flex gap-2">
                                <div class="min-w-0 flex-1">
                                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                                        <x-filament::input
                                            type="search"
                                            x-model="query"
                                            x-on:keydown.enter.prevent="search()"
                                            placeholder="Une ville, un lieu, une adresse…"
                                            aria-label="Chercher une adresse"
                                            autocomplete="off"
                                            data-map-search-input
                                        />
                                    </x-filament::input.wrapper>
                                </div>
                                <x-filament::button type="button" color="gray" x-on:click="search()" x-bind:disabled="searching || query.trim().length < 3">
                                    OK
                                </x-filament::button>
                            </div>

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
                        </div>
                    @endif

                    <div class="space-y-2">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">
                            Position
                            @if ($isPositionRequired())<sup class="text-danger-600">*</sup>@endif
                        </span>
                        <div class="grid grid-cols-2 gap-2">
                            <x-filament::input.wrapper prefix="Lat">
                                <x-filament::input type="number" step="0.00001" min="-90" max="90" title="Latitude" x-model.number="draft.lat" x-on:change="onCoordinatesTyped()" data-map-position-lat />
                            </x-filament::input.wrapper>
                            <x-filament::input.wrapper prefix="Lng">
                                <x-filament::input type="number" step="0.00001" min="-180" max="180" title="Longitude" x-model.number="draft.lng" x-on:change="onCoordinatesTyped()" data-map-position-lng />
                            </x-filament::input.wrapper>
                        </div>
                        @if ($hasMap)
                            <p class="text-xs text-gray-500 dark:text-gray-400">Cliquez sur la carte, déplacez le repère, ou saisissez des coordonnées.</p>
                        @endif
                        <div class="flex flex-wrap gap-2">
                            @if ($hasMap)
                                <x-filament::button type="button" color="gray" size="xs" icon="heroicon-m-viewfinder-circle" x-on:click="centerOnMarker()" x-bind:disabled="! draft.lat && draft.lat !== 0">
                                    Centrer sur le repère
                                </x-filament::button>
                            @endif
                            @unless ($isPositionRequired())
                                <x-filament::button type="button" color="gray" size="xs" icon="heroicon-m-x-mark" x-on:click="clearPosition()">
                                    Effacer la position
                                </x-filament::button>
                            @endunless
                        </div>
                    </div>

                    @foreach ($positionFields as $key => $positionField)
                        <div class="space-y-2">
                            <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $positionField['label'] }}</span>
                            <div class="flex gap-2">
                                <div class="min-w-0 flex-1">
                                    <x-filament::input.wrapper>
                                        <x-filament::input
                                            type="number"
                                            :step="$positionField['step']"
                                            :min="$positionField['min']"
                                            :max="$positionField['max']"
                                            x-model.number="draft.fields['{{ $key }}']"
                                            data-map-position-field="{{ $key }}"
                                        />
                                    </x-filament::input.wrapper>
                                </div>
                                @if ($hasMap && $positionField['kind'] === 'zoom')
                                    <x-filament::icon-button
                                        icon="heroicon-o-viewfinder-circle"
                                        color="gray"
                                        label="Utiliser le zoom actuel"
                                        tooltip="Utiliser le zoom actuel de la carte"
                                        x-on:click="captureZoom('{{ $key }}')"
                                    />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($hasMap)
                    <div class="min-w-0 space-y-2">
                        <div class="flex justify-end">
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

                        <div wire:ignore id="{{ $mapDomId }}" class="{{ $getMapHeight() }} min-h-[320px] w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"></div>
                    </div>
                @endif
            </div>

            <p x-show="error" x-cloak x-text="error" class="mt-3 text-sm text-danger-600 dark:text-danger-400" data-map-position-error></p>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-filament::button type="button" color="gray" x-on:click="cancel()" data-map-position-cancel>
                        Annuler
                    </x-filament::button>
                    <x-filament::button type="button" x-on:click="commit()" x-bind:disabled="saving" data-map-position-commit>
                        <span x-text="saving ? 'Enregistrement…' : 'Valider'"></span>
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>
</x-dynamic-component>
