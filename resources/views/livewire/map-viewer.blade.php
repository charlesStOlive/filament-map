@php
    $mapDomId = 'filament-map-' . $this->getId();
    $assetsPath = config('filament-map.leaflet.assets_path', 'vendor/filament-map/filament-map.js');
@endphp

<div class="{{ trim($width . ' ' . $height . ' ' . $class) }}">
    @if ($payload)
        <div
            id="{{ $mapDomId }}"
            class="h-full min-h-[300px] w-full"
            wire:ignore
            data-filament-map
        ></div>

        @once
            <link rel="stylesheet" href="{{ config('filament-map.leaflet.css_url') }}">
            <script src="{{ config('filament-map.leaflet.js_url') }}"></script>
            <script type="module" src="{{ asset($assetsPath) }}"></script>
        @endonce

        @script
            <script>
                window.dispatchEvent(new CustomEvent('filament-map:init', {
                    detail: {
                        id: @js($mapDomId),
                        payload: @js($payload),
                    },
                }))
            </script>
        @endscript
    @endif
</div>
