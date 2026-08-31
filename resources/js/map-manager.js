import { LeafletMapInstance } from './leaflet-map-instance.js'

export class FilamentMapManager {
    constructor() {
        this.instances = new Map()
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

    command(detail) {
        for (const [id, instance] of this.instances.entries()) {
            if (!instance.acceptsCommand(detail)) {
                continue
            }

            return {
                id,
                handled: instance.command(detail),
            }
        }

        return { id: null, handled: false }
    }
}
