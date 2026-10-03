{{--
    IconPicker : l'icône choisie (son dessin, son nom) et le popup qui parcourt le catalogue (route filament-map.icons,
    voir IconCatalog). L'état est le nom de l'icône, lié à Livewire par `$wire.$entangle`. Les dessins viennent des
    fichiers SVG des jeux d'icônes de l'application, jamais d'une saisie.
--}}
@php
    $statePath = $getStatePath();
    $modalId = 'filament-map-icon-picker-' . str($statePath)->slug('-');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle(@js($statePath), @js($isLive())),
            svg: @js($getStateSvg()),
            url: @js(route('filament-map.icons')),
            search: '',
            group: '',
            groups: {},
            icons: [],
            total: 0,
            loading: false,
            request: 0,
            open() {
                $dispatch('open-modal', { id: @js($modalId) })
                this.load()
            },
            async load() {
                const request = ++this.request
                this.loading = true

                try {
                    const response = await fetch(`${this.url}?${new URLSearchParams({ search: this.search, group: this.group })}`, { headers: { Accept: 'application/json' } })
                    const data = await response.json()

                    // Une réponse plus ancienne que la dernière recherche est ignorée.
                    if (request !== this.request) return

                    this.groups = data.groups
                    this.icons = data.icons
                    this.total = data.total
                } finally {
                    if (request === this.request) this.loading = false
                }
            },
            pick(icon) {
                this.state = icon.name
                this.svg = icon.svg
                $dispatch('close-modal', { id: @js($modalId) })
            },
            clear() {
                this.state = null
                this.svg = null
            },
        }"
        class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-2 shadow-sm dark:border-white/10 dark:bg-gray-900"
        data-icon-picker
    >
        <div class="grid size-10 shrink-0 place-items-center rounded-md bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-200 [&_svg]:size-6" x-html="svg ?? ''"></div>

        <div class="min-w-0 flex-1 text-sm">
            <p class="truncate font-mono text-gray-950 dark:text-white" x-show="state" x-text="state"></p>
            <p class="text-gray-500 dark:text-gray-400" x-show="! state">{{ $getEmptyLabel() }}</p>
        </div>

        @unless ($isDisabled())
            <x-filament::button size="sm" color="gray" icon="heroicon-m-magnifying-glass" x-on:click="open()">
                Choisir…
            </x-filament::button>

            @if ($isClearable())
                <x-filament::icon-button icon="heroicon-m-x-mark" color="gray" label="Retirer" x-show="state" x-on:click="clear()" />
            @endif
        @endunless

        <x-filament::modal :id="$modalId" width="5xl" teleport="body">
            <x-slot name="heading">Choisir une icône</x-slot>

            <div class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_16rem]">
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input type="search" placeholder="Chercher : map, camera, plane…" x-model="search" x-on:input.debounce.300ms="load()" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <x-filament::input.select x-model="group" x-on:change="load()">
                            <option value="">Tous les jeux</option>
                            <template x-for="(label, key) in groups" x-bind:key="key">
                                <option x-bind:value="key" x-text="label"></option>
                            </template>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <span x-show="loading">Recherche…</span>
                    <span x-show="! loading && total === 0">Aucune icône ne correspond.</span>
                    <span x-show="! loading && total > 0 && total <= icons.length" x-text="`${total} icône${total > 1 ? 's' : ''}`"></span>
                    <span x-show="! loading && total > icons.length" x-text="`${icons.length} affichées sur ${total} : affinez la recherche ou choisissez un jeu.`"></span>
                </p>

                <div class="grid max-h-[60vh] grid-cols-4 gap-2 overflow-y-auto sm:grid-cols-6 lg:grid-cols-8">
                    <template x-for="icon in icons" x-bind:key="icon.name">
                        <button
                            type="button"
                            x-on:click="pick(icon)"
                            x-bind:title="icon.name"
                            x-bind:class="state === icon.name ? 'ring-2 ring-primary-600 dark:ring-primary-500' : 'ring-1 ring-gray-950/5 dark:ring-white/10'"
                            class="flex flex-col items-center gap-1 rounded-lg bg-white p-2 text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-white/5"
                        >
                            <span class="grid size-8 place-items-center [&_svg]:size-6" x-html="icon.svg"></span>
                            <span class="w-full truncate text-center text-[10px] text-gray-500 dark:text-gray-400" x-text="icon.label"></span>
                        </button>
                    </template>
                </div>
            </div>
        </x-filament::modal>
    </div>
</x-dynamic-component>
