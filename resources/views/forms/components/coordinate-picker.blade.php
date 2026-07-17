<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
        x-data="{
            latitudeField: @js($getLatitudeField()),
            longitudeField: @js($getLongitudeField()),
            enabled: false,
            enablePicker() {
                this.enabled = true
                window.dispatchEvent(new CustomEvent('filament-map:enable-coordinate-picker', {
                    detail: {
                        latitudeField: this.latitudeField,
                        longitudeField: this.longitudeField,
                    },
                }))
            },
        }"
    >
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="font-medium">Selection depuis la carte</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Saisissez les coordonnees a la main ou activez le picker pour recuperer latitude et longitude depuis une carte cible.
                </div>
            </div>

            <x-filament::button type="button" color="gray" x-on:click="enablePicker()">
                Choisir sur la carte
            </x-filament::button>
        </div>
    </div>
</x-dynamic-component>
