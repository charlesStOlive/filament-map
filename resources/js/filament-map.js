import { FilamentMapManager } from './map-manager.js'

const manager = new FilamentMapManager()

window.FilamentMap = manager

const bootPendingMaps = () => {
    if (!window.L) {
        window.setTimeout(bootPendingMaps, 50)
        return
    }

    for (const [id, payload] of Object.entries(window.__filamentMapPending ?? {})) {
        manager.init(id, payload)
        delete window.__filamentMapPending[id]
    }
}

bootPendingMaps()

window.addEventListener('filament-map:init', (event) => {
    if (!window.L) {
        window.__filamentMapPending = window.__filamentMapPending || {}
        window.__filamentMapPending[event.detail.id] = event.detail.payload
        return
    }

    manager.init(event.detail.id, event.detail.payload)

    if (window.__filamentMapPending) {
        delete window.__filamentMapPending[event.detail.id]
    }
})

window.addEventListener('filament-map:update', (event) => manager.update(event.detail.id, event.detail.payload))
window.addEventListener('filament-map:destroy', (event) => manager.destroy(event.detail.id))

window.addEventListener('filament-map:command', (event) => {
    const result = manager.command(event.detail ?? {})
    const eventName = result.handled
        ? 'filament-map:command-executed'
        : 'filament-map:command-error'

    window.dispatchEvent(new CustomEvent(eventName, {
        detail: {
            ...event.detail,
            instanceId: result.id,
            message: result.message,
        },
    }))
})

window.dispatchEvent(new CustomEvent('filament-map:ready', { detail: { manager } }))
