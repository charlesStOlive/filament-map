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
        this.handleMapClick = (event) => this.coordinatesPicked(event.latlng)
    }

    mount() {
        const center = this.payload.map.center

        this.map = L.map(this.element, {
            zoomControl: this.payload.controls?.zoom ?? true,
            minZoom: this.payload.map.minZoom ?? undefined,
            maxZoom: this.payload.map.maxZoom ?? undefined,
            ...(this.payload.map.options ?? {}),
        }).setView([center.lat, center.lng], this.payload.map.zoom)

        this.configureInteraction()
        this.renderLayers()
        this.renderPoints()
        this.renderLayerControl()

        if (this.payload.state?.fitBounds) {
            this.fitToVisibleContent()
        }

        this.focusSelectedPoint()
    }

    update(payload) {
        const previousMap = this.payload.map
        this.payload = payload

        if (
            previousMap.id !== payload.map.id
            || previousMap.center?.lat !== payload.map.center?.lat
            || previousMap.center?.lng !== payload.map.center?.lng
            || previousMap.zoom !== payload.map.zoom
        ) {
            this.map.setView(
                [payload.map.center.lat, payload.map.center.lng],
                payload.map.zoom,
            )
        }

        this.configureInteraction()
        this.clearLayers()
        this.renderLayers()
        this.renderPoints()
        this.renderLayerControl()

        if (this.payload.state?.fitBounds) {
            this.fitToVisibleContent()
        }

        this.focusSelectedPoint()
    }

    destroy() {
        this.map?.off('click', this.handleMapClick)
        this.layerControl?.remove()
        this.map?.remove()
        this.map = null
        this.layerControl = null
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

                leafletLayer.on?.('filament-map:ready', () => {
                    if (
                        this.payload.state?.fitBounds
                        && this.layers.get(layer.key) === leafletLayer
                    ) {
                        this.fitToVisibleContent()
                    }
                })

                leafletLayer.on?.('filament-map:error', (event) => {
                    window.dispatchEvent(new CustomEvent('filament-map:layer-error', {
                        detail: {
                            mapId: this.payload.map.id,
                            scope: this.payload.state?.eventScope,
                            layer,
                            message: event.error?.message ?? 'Impossible de charger la couche.',
                        },
                    }))
                })
            }
        }
    }

    renderPoints() {
        const markerLayer = addMarkerLayer(this.map, this.payload.points ?? [], {
            selectedPointId: this.payload.state?.selectedPointId,
            onPointClick: (point) => this.pointClicked(point),
        })

        if (markerLayer) {
            this.layers.set('__points', markerLayer)
        }
    }

    renderLayerControl() {
        if (!(this.payload.controls?.layers ?? true)) {
            return
        }

        const baseLayers = {}
        const overlays = {}

        for (const layer of this.payload.layers ?? []) {
            const leafletLayer = this.layers.get(layer.key)

            if (!leafletLayer) {
                continue
            }

            const label = layer.name || layer.key

            if (layer.type === 'tile') {
                baseLayers[label] = leafletLayer
            } else {
                overlays[label] = leafletLayer
            }
        }

        if (this.layers.has('__points')) {
            overlays.Points = this.layers.get('__points')
        }

        if (Object.keys(baseLayers).length || Object.keys(overlays).length) {
            this.layerControl = L.control.layers(baseLayers, overlays).addTo(this.map)
        }
    }

    clearLayers() {
        this.layerControl?.remove()
        this.layerControl = null

        for (const layer of this.layers.values()) {
            layer.filamentMapDisposed = true
            layer.removeFrom(this.map)
        }

        this.layers.clear()
    }

    fitToVisibleContent() {
        const bounds = L.latLngBounds([])

        for (const layer of this.layers.values()) {
            if (!this.map.hasLayer(layer) || typeof layer.getBounds !== 'function') {
                continue
            }

            const layerBounds = layer.getBounds()

            if (layerBounds?.isValid()) {
                bounds.extend(layerBounds)
            }
        }

        if (bounds.isValid()) {
            this.map.fitBounds(bounds, { padding: [24, 24] })
        }
    }

    configureInteraction() {
        this.map.off('click', this.handleMapClick)

        if (this.payload.state?.interactive ?? true) {
            this.map.on('click', this.handleMapClick)
        }
    }

    focusSelectedPoint() {
        const pointId = this.payload.state?.selectedPointId
        const marker = this.layers.get('__points')?.getFilamentMarker?.(pointId)

        if (!marker) {
            return
        }

        this.map.panTo(marker.getLatLng())

        if (marker.getPopup()) {
            marker.openPopup()
        } else if (marker.getTooltip()) {
            marker.openTooltip()
        }
    }

    acceptsCommand(detail = {}) {
        const sameMap = detail.mapId === null
            || detail.mapId === undefined
            || String(detail.mapId) === String(this.payload.map.id)
        const scope = this.payload.state?.eventScope
        const sameScope = !detail.scope || !scope || detail.scope === scope

        return sameMap && sameScope
    }

    command(detail = {}) {
        if (!this.acceptsCommand(detail)) {
            return false
        }

        if (['show-layer', 'hide-layer', 'toggle-layer'].includes(detail.command)) {
            return this.setLayerVisibility(detail.target, detail.command)
        }

        return false
    }

    setLayerVisibility(layerKey, command) {
        const layer = this.layers.get(String(layerKey))

        if (!layer) {
            return false
        }

        const visible = this.map.hasLayer(layer)
        const shouldShow = command === 'show-layer'
            || (command === 'toggle-layer' && !visible)

        if (shouldShow && !visible) {
            layer.addTo(this.map)
        }

        if (!shouldShow && visible) {
            layer.removeFrom(this.map)
        }

        return true
    }

    coordinatesPicked(position) {
        const detail = this.eventDetail({
            lat: position.lat,
            lng: position.lng,
        })

        window.dispatchEvent(new CustomEvent('filament-map:coordinates-picked', {
            detail,
        }))
        window.Livewire?.dispatch?.('filament-map-coordinates-picked', detail)
    }

    pointClicked(point) {
        const detail = this.eventDetail({ point })

        window.dispatchEvent(new CustomEvent('filament-map:point-clicked', {
            detail,
        }))
        window.Livewire?.dispatch?.('filament-map-point-clicked', detail)
    }

    eventDetail(detail = {}) {
        return {
            mapId: this.payload.map.id,
            scope: this.payload.state?.eventScope,
            ...detail,
        }
    }
}
