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
        })"
        x-init="init()"
        class="space-y-3"
    >
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
            @endif

            <span x-text="summary"></span>
        </div>
    </div>
</x-dynamic-component>
