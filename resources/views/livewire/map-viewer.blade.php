@php
    $assetsPath = config('filament-map.leaflet.assets_path', 'vendor/filament-map/filament-map.js');
@endphp

<div
    class="{{ trim($width . ' ' . $class) }}"
    data-filament-map-viewer
    data-filament-map-scope="{{ $eventScope }}"
>
    @if ($payload)
        @if ($showRefresh)
            <div class="mb-2 flex justify-end">
                <button
                    type="button"
                    wire:click="refreshMap"
                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                >
                    Actualiser la carte enregistrée
                </button>
            </div>
        @endif

        <div wire:ignore class="{{ $height }}">
            <div
                id="{{ $mapDomId }}"
                class="h-full min-h-[300px] w-full overflow-hidden rounded-lg"
                data-filament-map
            ></div>
        </div>
    @else
        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            Aucune carte enregistrée ne peut encore être affichée.
        </div>
    @endif
</div>

@assets
    <link rel="stylesheet" href="{{ config('filament-map.leaflet.css_url', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css') }}">
    <script src="{{ config('filament-map.leaflet.js_url', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js') }}"></script>
    <script type="module" src="{{ asset($assetsPath) }}"></script>
@endassets

@if ($payload)
    @script
        <script>
            const id = @js($mapDomId);
            const payload = @js($payload);

            window.__filamentMapPending = window.__filamentMapPending || {};
            window.__filamentMapPending[id] = payload;
            window.dispatchEvent(new CustomEvent('filament-map:init', {
                detail: { id, payload },
            }));
        </script>
    @endscript
@endif
