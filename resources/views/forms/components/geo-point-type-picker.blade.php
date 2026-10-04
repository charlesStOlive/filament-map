{{--
    GeoPointTypePicker : le type choisi (son marqueur, son nom, ce qu'il montre) et le popup qui présente les types en
    cartes carrées — recherche par nom, filtre par contenu, classement. L'état est l'identifiant du type, lié à Livewire
    par `$wire.$entangle`.

    Le résumé est dessiné par resources/js/marker-preview.js d'après l'apparence du type choisie dans `types` ; les cartes
    du popup par partials/marker-preview (`tile`). Tout est dessiné dans le navigateur : `wire:ignore` empêche Livewire de
    remettre des cadres vides en réaffichant la page.
    Le popup s'ouvre et se ferme par un événement envoyé sur `window` (`modal()`), jamais par `$dispatch` depuis le champ :
    dans un modal (la Configuration d'un voyage, une action), l'événement remonterait jusqu'à lui, qui l'arrête
    (`x-on:open-modal.stop` du composant modal de Filament) — le popup ne l'entendrait jamais.
--}}
@php
    $statePath = $getStatePath();
    $modalId = 'filament-map-type-picker-' . str($statePath)->slug('-');
    $cards = $getCards();
    $placeholder = $getPlaceholder();
    $placeholderType = $getPlaceholderType();
    $file = public_path('vendor/filament-map/marker-preview.js');
    $moduleUrl = asset('vendor/filament-map/marker-preview.js') . (is_file($file) ? '?v=' . filemtime($file) : '');
    $light = 'background-color: #eef1ea; background-image: linear-gradient(#dfe5da 1px, transparent 1px), linear-gradient(90deg, #dfe5da 1px, transparent 1px); background-size: 16px 16px;';
    // De quoi filtrer, classer et résumer chaque type ; son apparence avec l'image qu'il montre à défaut (la sienne, sinon un exemple).
    $types = collect($cards)->mapWithKeys(fn (array $card): array => [$card['id'] => [
        'name' => $card['name'],
        'kind' => $card['kind'],
        'size' => $card['size'],
        'order' => $card['order'],
        'status' => $card['preview']['status']['label'],
        'appearance' => $card['preview']['appearances'][isset($card['preview']['appearances']['type']) ? 'type' : $card['preview']['default']],
    ]])->all();
    $grid = (new \Illuminate\View\ComponentAttributeBag)->grid($getColumns())->class(['gap-3']);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="{
            state: $wire.$entangle(@js($statePath), @js($isLive())),
            modal(action) {
                window.dispatchEvent(new CustomEvent(`${action}-modal`, { detail: { id: @js($modalId) } }))
            },
            types: @js((object) $types),
            placeholderType: @js($placeholderType),
            search: '',
            kind: '',
            sort: 'order',
            draw: null,
            async init() {
                const { drawMarkerStage } = await import(@js($moduleUrl))

                this.draw = () => {
                    const type = this.current()
                    const stage = this.$refs.current

                    stage.querySelector('[data-marker-holder]').replaceChildren()
                    if (type) drawMarkerStage(stage, type.appearance)
                }
                this.draw()
                this.$watch('state', () => this.draw())
                new ResizeObserver(() => this.draw()).observe(this.$refs.current)
            },
            current() {
                return this.types[this.state] ?? this.types[this.placeholderType] ?? null
            },
            chosen() {
                return this.state !== null && this.state !== '' && this.types[this.state] !== undefined
            },
            plain(text) {
                return text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
            },
            visible(id) {
                const type = this.types[id]
                const words = this.plain(this.search).split(/\s+/).filter(Boolean)

                return (this.kind === '' || type.kind === this.kind) && words.every((word) => this.plain(type.name).includes(word))
            },
            rank(id) {
                const by = {
                    order: (a, b) => a.order - b.order || a.name.localeCompare(b.name),
                    name: (a, b) => a.name.localeCompare(b.name),
                    'size-asc': (a, b) => a.size - b.size || a.name.localeCompare(b.name),
                    'size-desc': (a, b) => b.size - a.size || a.name.localeCompare(b.name),
                }[this.sort]

                return Object.keys(this.types).sort((a, b) => by(this.types[a], this.types[b])).indexOf(String(id))
            },
            pick(id) {
                this.state = id
                this.modal('close')
            },
        }"
        class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-2 shadow-sm dark:border-white/10 dark:bg-gray-900"
        data-geo-point-type-picker
    >
        <div x-ref="current" data-marker-stage class="relative size-16 shrink-0 overflow-hidden rounded-md" style="{{ $light }}">
            <div data-marker-holder></div>
        </div>

        <div class="min-w-0 flex-1 text-sm">
            <p class="truncate font-medium text-gray-950 dark:text-white" x-show="chosen()" x-text="types[state]?.name"></p>
            <p class="truncate text-gray-500 dark:text-gray-400" x-show="! chosen()">{{ $placeholder ?? 'Aucun type choisi' }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400" x-show="current()" x-text="current()?.status"></p>
        </div>

        @unless ($isDisabled())
            <x-filament::button size="sm" color="gray" icon="heroicon-m-squares-2x2" x-on:click="modal('open')">
                Choisir…
            </x-filament::button>
        @endunless

        <x-filament::modal :id="$modalId" width="6xl" teleport="body">
            <x-slot name="heading">{{ $getLabel() }}</x-slot>

            @if ($cards === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Aucun type de point actif : créez-en un dans « Types de points ».
                </p>
            @else
                <div class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_14rem]">
                        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                            <x-filament::input type="search" placeholder="Chercher un type par son nom" x-model="search" />
                        </x-filament::input.wrapper>

                        <x-filament::input.wrapper>
                            <x-filament::input.select x-model="kind" aria-label="Contenu">
                                <option value="">Tous les contenus</option>
                                @foreach ($getKinds() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>

                        <x-filament::input.wrapper>
                            <x-filament::input.select x-model="sort" aria-label="Classement">
                                <option value="order">Par ordre</option>
                                <option value="name">Par nom</option>
                                <option value="size-asc">Du plus petit au plus grand</option>
                                <option value="size-desc">Du plus grand au plus petit</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    <div {{ $grid }}>
                        @if (filled($placeholder))
                            <button
                                type="button"
                                x-on:click="pick(null)"
                                x-bind:class="! chosen() ? 'ring-2 ring-primary-600 dark:ring-primary-500' : 'ring-1 ring-gray-950/5 dark:ring-white/10'"
                                class="flex flex-col overflow-hidden rounded-xl bg-white text-start shadow-sm dark:bg-gray-900"
                                style="order: -1"
                            >
                                <div class="grid aspect-square w-full place-items-center bg-gray-50 p-2 text-center text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                    {{ $placeholder }}
                                </div>
                                <p class="p-2 text-xs font-medium text-gray-950 dark:text-white">Par défaut</p>
                            </button>
                        @endif

                        @foreach ($cards as $card)
                            <button
                                type="button"
                                x-on:click="pick(@js($card['id']))"
                                x-show="visible(@js($card['id']))"
                                x-bind:style="{ order: rank(@js($card['id'])) }"
                                x-bind:class="String(state) === @js((string) $card['id']) ? 'ring-2 ring-primary-600 dark:ring-primary-500' : 'ring-1 ring-gray-950/5 dark:ring-white/10'"
                                class="flex flex-col overflow-hidden rounded-xl bg-white text-start shadow-sm dark:bg-gray-900"
                                title="{{ $card['preview']['status']['detail'] }}"
                                data-geo-point-type-option="{{ $card['id'] }}"
                            >
                                @include('filament-map::partials.marker-preview', ['preview' => $card['preview'], 'variant' => 'tile'])

                                <div class="min-w-0 p-2 text-xs">
                                    <p class="truncate font-medium text-gray-950 dark:text-white">{{ $card['name'] }}</p>
                                    <p class="text-gray-500 dark:text-gray-400">
                                        {{ (int) $card['size'] }} % ·
                                        @if ($card['kind'] === 'image')
                                            <span class="text-success-600 dark:text-success-400">Avec image</span>
                                        @else
                                            {{ $getKinds()[$card['kind']] ?? '' }}
                                        @endif
                                    </p>
                                </div>
                            </button>
                        @endforeach
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400" x-show="Object.keys(types).every((id) => ! visible(id))">
                        Aucun type ne correspond.
                    </p>
                </div>
            @endif
        </x-filament::modal>
    </div>
</x-dynamic-component>
