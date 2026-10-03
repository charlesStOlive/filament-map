@php
    $mapDomId = 'filament-map-layer-preview-' . str($getStatePath())->slug('-');
    $statePath = $getStatePath();
    $parentStatePath = str($statePath)->beforeLast('.')->toString();
    $fieldPath = fn (string $field): string => filled($parentStatePath) ? "{$parentStatePath}.{$field}" : $field;
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="filamentMapLayerPreview({
            id: @js($mapDomId),
            maps: @js($getPreviewScenes()),
            fields: {
                map: @js($fieldPath($getSceneField())),
                type: @js($fieldPath($getTypeField())),
                sourceType: @js($fieldPath($getSourceTypeField())),
                sourceUrl: @js($fieldPath($getSourceUrlField())),
                sourcePath: @js($fieldPath($getSourcePathField())),
                sourceJson: @js($fieldPath($getSourceJsonField())),
                style: @js($fieldPath($getStyleField())),
                styleRules: @js($fieldPath($getStyleRulesField())),
                options: @js($fieldPath($getOptionsField())),
                visible: @js($fieldPath($getVisibleField())),
            },
            defaults: {
                lat: @js(config('filament-map.default.lat', 48.8566)),
                lng: @js(config('filament-map.default.lng', 2.3522)),
                zoom: @js(config('filament-map.default.zoom', 10)),
            },
            tiles: {
                url: @js(config('filament-map.tiles.url')),
                attribution: @js(config('filament-map.tiles.attribution')),
            },
            keys: @js(\CharlesStOlive\FilamentMap\Support\MapKeys::all()),
            files: {
                urlPrefix: @js(rtrim(\Illuminate\Support\Facades\Storage::disk(config('filament-map.files.disk', 'public'))->url(''), '/') . '/'),
            },
        })"
        x-init="init()"
        class="space-y-3"
    >
        @once
            <link rel="stylesheet" href="{{ config('filament-map.maplibre.css_url') }}">
            <script src="{{ config('filament-map.maplibre.js_url') }}"></script>
            <script src="{{ asset('vendor/filament-map/map-layer-preview.js') }}"></script>
        @endonce

        <div id="{{ $mapDomId }}" class="{{ $getHeight() }} min-h-[320px] w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"></div>

        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
            <x-filament::button type="button" color="gray" size="xs" x-on:click="refresh()">
                Actualiser l'aperçu
            </x-filament::button>

            <x-filament::button type="button" color="gray" size="xs" x-on:click="fitLayer()">
                Recadrer sur la couche
            </x-filament::button>

            <span
                x-text="message"
                x-bind:class="{
                    'text-success-600 dark:text-success-400': status === 'ok',
                    'text-danger-600 dark:text-danger-400 font-medium': status === 'error',
                    'animate-pulse': status === 'loading',
                }"
            ></span>
        </div>
    </div>
</x-dynamic-component>
