{{--
    L'aperçu d'un marqueur (Support\MarkerPreview::for()) : dessiné par resources/js/layers/marker-element.js, le code
    de la carte, et posé dans chaque scène (`data-marker-stage`) par resources/js/marker-preview.js, comme MapLibre le
    poserait — ancrage, décalage, rotation —, le dessin réel centré. `$compact` : la seule taille réelle, pour la liste
    des types.

    L'image d'exemple choisie est gardée pour la session (sessionStorage) : l'aperçu est redessiné à chaque saisie,
    `wire:key` changeant avec lui.
--}}
@php
    $compact ??= false;
    $file = public_path('vendor/filament-map/marker-preview.js');
    $moduleUrl = asset('vendor/filament-map/marker-preview.js') . (is_file($file) ? '?v=' . filemtime($file) : '');
    $status = $preview['status'];
    $light = 'background-color: #eef1ea; background-image: linear-gradient(#dfe5da 1px, transparent 1px), linear-gradient(90deg, #dfe5da 1px, transparent 1px); background-size: 16px 16px;';
    $dark = 'background-color: #22303a; background-image: linear-gradient(#2c3c47 1px, transparent 1px), linear-gradient(90deg, #2c3c47 1px, transparent 1px); background-size: 16px 16px;';
    $size = $preview['size'];
@endphp

<div
    wire:key="filament-map-marker-preview-{{ md5(json_encode($preview)) }}"
    x-data="{
        appearances: @js($preview['appearances']),
        {{-- Dans la liste, l'image du type quand il en a une : c'est elle qu'il montre à défaut. --}}
        sample: @js($compact && isset($preview['appearances']['type']) ? 'type' : $preview['default']),
        draw: null,
        async init() {
            try {
                const kept = @js($compact) ? null : sessionStorage.getItem('filament-map-marker-sample')
                if (kept && this.appearances[kept]) this.sample = kept
            } catch (error) {}

            const { drawMarkerStage } = await import(@js($moduleUrl))

            this.draw = () => this.$root.querySelectorAll('[data-marker-stage]').forEach((stage) => drawMarkerStage(stage, this.appearances[this.sample], {
                fit: stage.dataset.fit === '1',
                outlineSlot: stage.dataset.outline === '1',
            }))
            this.draw()
        },
        choose(key) {
            this.sample = key
            try { sessionStorage.setItem('filament-map-marker-sample', key) } catch (error) {}
            this.draw?.()
        },
    }"
    @class(['flex flex-col gap-3' => ! $compact, 'flex items-center gap-2' => $compact])
>
    @if ($compact)
        <div data-marker-stage class="relative size-14 shrink-0 overflow-hidden rounded-md" style="{{ $light }}">
            <div data-marker-holder></div>
        </div>
        <x-filament::badge :color="$status['color']" size="sm">{{ $status['label'] }}</x-filament::badge>
    @else
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::badge :color="$status['color']">{{ $status['label'] }}</x-filament::badge>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ (int) $size['percent'] }} % : {{ rtrim(rtrim(number_format($size['width'], 1, ',', ''), '0'), ',') }} × {{ rtrim(rtrim(number_format($size['height'], 1, ',', ''), '0'), ',') }} px,
                ancré {{ \CharlesStOlive\FilamentMap\Support\MarkerPreview::anchorLabel($preview['anchor']) }}@if ($preview['offset']['x'] || $preview['offset']['y']), décalé de {{ (float) $preview['offset']['x'] }} % et {{ (float) $preview['offset']['y'] }} %@endif@if ($preview['rotation']), pivoté de {{ (float) $preview['rotation'] }}°@endif
            </span>
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $status['detail'] }}</p>

        <div class="grid grid-cols-2 gap-2">
            <div data-marker-stage class="relative h-24 overflow-hidden rounded-lg" style="{{ $light }}">
                <div data-marker-holder></div>
                <span class="absolute bottom-1 left-2 text-[10px] text-gray-500">Taille réelle</span>
            </div>
            <div data-marker-stage class="relative h-24 overflow-hidden rounded-lg" style="{{ $dark }}">
                <div data-marker-holder></div>
                <span class="absolute bottom-1 left-2 text-[10px] text-gray-300">Fond sombre</span>
            </div>
        </div>

        <div data-marker-stage data-fit="1" data-outline="1" class="relative h-64 overflow-hidden rounded-lg" style="{{ $light }}">
            <div data-marker-holder></div>
            {{-- La position du point : là où l'ancrage pose le marqueur, avant décalage. --}}
            <div data-marker-cross class="pointer-events-none absolute size-0">
                <div class="absolute h-px w-6 -translate-x-1/2 bg-rose-500/80"></div>
                <div class="absolute h-6 w-px -translate-y-1/2 bg-rose-500/80"></div>
            </div>
            <span class="absolute bottom-1 left-2 text-[10px] text-gray-500">
                Agrandi <span data-marker-zoom></span> — en pointillés, la zone de contenu ; la croix, la position du point
            </span>
        </div>

        @if ($preview['samples'] !== [])
            <div class="flex flex-wrap items-center gap-1">
                <span class="me-1 text-xs text-gray-500 dark:text-gray-400">Image d’exemple :</span>
                @foreach ($preview['samples'] as $key => $label)
                    <button
                        type="button"
                        x-on:click="choose(@js($key))"
                        x-bind:class="sample === @js($key) ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200'"
                        class="rounded-md px-2 py-1 text-xs font-medium"
                    >{{ $label }}</button>
                @endforeach
            </div>
        @endif
    @endif
</div>
