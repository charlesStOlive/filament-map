import { addGeoJsonLayer } from './layers/geojson-layer.js'
import { addMarkerLayer } from './layers/marker-layer.js'
import { addSvgOverlayLayer } from './layers/svg-overlay-layer.js'
import { addTileLayer } from './layers/tile-layer.js'

export class LeafletMapInstance {
    constructor(element, payload) {
        this.element = element
        this.payload = payload
        this.map = null
        this.layers = new Map()
        this.layerControl = null
    }

    mount() {
        const center = this.payload.map.center

        this.map = L.map(this.element, {
            zoomControl: this.payload.controls?.zoom ?? true,
            minZoom: this.payload.map.minZoom ?? undefined,
            maxZoom: this.payload.map.maxZoom ?? undefined,
            ...(this.payload.map.options ?? {}),
        }).setView([center.lat, center.lng], this.payload.map.zoom)

        this.renderLayers()
        this.renderPoints()

        if (this.payload.state?.fitBounds) {
            this.fitToVisibleContent()
        }
    }

    update(payload) {
        this.payload = payload
        this.clearLayers()
        this.renderLayers()
        this.renderPoints()
    }

    destroy() {
        this.map?.remove()
        this.map = null
        this.layers.clear()
    }

    renderLayers() {
        for (const layer of this.payload.layers ?? []) {
            let leafletLayer = null

            if (layer.type === 'tile') {
                leafletLayer = addTileLayer(this.map, layer)
            }

            if (layer.type === 'geojson') {
                leafletLayer = addGeoJsonLayer(this.map, layer)
            }

            if (layer.type === 'svg_overlay') {
                leafletLayer = addSvgOverlayLayer(this.map, layer)
            }

            if (leafletLayer) {
                this.layers.set(layer.key, leafletLayer)
            }
        }
    }

    renderPoints() {
        const markerLayer = addMarkerLayer(this.map, this.payload.points ?? [])

        if (markerLayer) {
            this.layers.set('__points', markerLayer)
        }
    }

    clearLayers() {
        for (const layer of this.layers.values()) {
            layer.removeFrom(this.map)
        }

        this.layers.clear()
    }

    fitToVisibleContent() {
        const group = L.featureGroup([...this.layers.values()])

        if (group.getLayers().length > 0) {
            this.map.fitBounds(group.getBounds(), { padding: [24, 24] })
        }
    }
}
