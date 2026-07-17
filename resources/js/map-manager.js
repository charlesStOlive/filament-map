import { LeafletMapInstance } from './leaflet-map-instance.js'

export class FilamentMapManager {
    constructor() {
        this.instances = new Map()
    }

    init(id, payload) {
        const element = document.getElementById(id)

        if (!element || !window.L) {
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
        this.instances.get(id)?.update(payload)
    }

    destroy(id) {
        this.instances.get(id)?.destroy()
        this.instances.delete(id)
    }
}
