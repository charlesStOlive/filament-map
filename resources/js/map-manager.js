import { LeafletMapInstance } from './leaflet-map-instance.js'

export class FilamentMapManager {
    constructor() {
        this.instances = new Map()
        this.commands = new Map()
        this.registerBuiltinCommands()
    }

    init(id, payload) {
        const element = document.getElementById(id)

        if (!element || !window.L || !payload?.map) {
            return
        }

        if (this.instances.has(id)) {
            this.instances.get(id).update(payload)
            return
        }

        const instance = new LeafletMapInstance(element, payload)
        this.instances.set(id, instance)
        instance.mount()
    }

    update(id, payload) {
        if (!this.instances.has(id)) {
            this.init(id, payload)
            return
        }

        this.instances.get(id).update(payload)
    }

    destroy(id) {
        this.instances.get(id)?.destroy()
        this.instances.delete(id)
    }

    registerCommand(name, handler) {
        if (!name || typeof handler !== 'function') {
            throw new Error('Une commande de carte requiert un nom et une fonction.')
        }

        this.commands.set(name, handler)
        return this
    }

    unregisterCommand(name) {
        this.commands.delete(name)
        return this
    }

    command(detail = {}) {
        const handler = this.commands.get(detail.command)

        if (!handler) {
            return { id: null, handled: false, message: `Commande inconnue : ${detail.command}` }
        }

        for (const [id, instance] of this.instances.entries()) {
            if (!instance.acceptsCommand(detail)) {
                continue
            }

            return {
                id,
                handled: handler({ instance, detail, manager: this }) !== false,
            }
        }

        return { id: null, handled: false, message: 'Aucune carte ne correspond à la commande.' }
    }

    registerBuiltinCommands() {
        this.registerCommand('show-layer', ({ instance, detail }) => instance.setLayerVisibility(detail.target, 'show-layer'))
        this.registerCommand('hide-layer', ({ instance, detail }) => instance.setLayerVisibility(detail.target, 'hide-layer'))
        this.registerCommand('toggle-layer', ({ instance, detail }) => instance.setLayerVisibility(detail.target, 'toggle-layer'))
        this.registerCommand('zoom-to', ({ instance, detail }) => {
            const zoom = Number(detail.payload?.zoom)

            if (!Number.isFinite(zoom)) {
                return false
            }

            instance.map.setZoom(zoom, { animate: detail.payload?.animate ?? true })
            return true
        })
        this.registerCommand('move-to', ({ instance, detail }) => {
            const lat = Number(detail.payload?.lat)
            const lng = Number(detail.payload?.lng)
            const zoom = detail.payload?.zoom === null || detail.payload?.zoom === undefined
                ? instance.map.getZoom()
                : Number(detail.payload.zoom)

            if (![lat, lng, zoom].every(Number.isFinite)) {
                return false
            }

            instance.map.setView([lat, lng], zoom, { animate: detail.payload?.animate ?? true })
            return true
        })
        this.registerCommand('fit-bounds', ({ instance }) => {
            instance.fitToVisibleContent()
            return true
        })
        this.registerCommand('highlight-feature', ({ instance, detail }) => {
            const layer = instance.layers.get(String(detail.target))
            const property = detail.payload?.property
            const expected = detail.payload?.value
            const style = detail.payload?.style ?? { color: '#f59e0b', weight: 5, fillOpacity: 0.7 }

            if (!layer?.eachLayer || !property) {
                return false
            }

            layer.eachLayer((featureLayer) => {
                const matches = String(featureLayer.feature?.properties?.[property]) === String(expected)

                if (matches) {
                    featureLayer.setStyle?.(style)
                    featureLayer.bringToFront?.()
                }
            })

            return true
        })
    }
}
