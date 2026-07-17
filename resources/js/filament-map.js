import { FilamentMapManager } from './map-manager.js'

const manager = new FilamentMapManager()

window.FilamentMap = manager

window.addEventListener('filament-map:init', (event) => {
    manager.init(event.detail.id, event.detail.payload)
})

window.addEventListener('filament-map:update', (event) => {
    manager.update(event.detail.id, event.detail.payload)
})

window.addEventListener('filament-map:destroy', (event) => {
    manager.destroy(event.detail.id)
})
