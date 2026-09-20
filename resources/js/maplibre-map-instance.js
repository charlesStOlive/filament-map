import { addGeoJsonLayer } from './layers/geojson-layer.js'
import { addMarkerLayer } from './layers/marker-layer.js'
import { addSvgOverlayLayer } from './layers/svg-overlay-layer.js'
import { addTileLayer } from './layers/tile-layer.js'

/**
 * Style minimal utilisé quand aucune couche "style" (TileCat & co) n'est
 * active par défaut : un simple fond OpenStreetMap raster, pour que la
 * carte affiche toujours quelque chose.
 */
const DEFAULT_STYLE = {
    version: 8,
    sources: {
        'filament-map-default-osm': {
            type: 'raster',
            tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
            tileSize: 256,
            attribution: '© OpenStreetMap contributors',
        },
    },
    layers: [{ id: 'filament-map-default-osm', type: 'raster', source: 'filament-map-default-osm' }],
}

export class MapLibreMapInstance {
    /**
     * Registre des renderers par type de couche — le point d'extension
     * "driver" côté JS : une app peut enregistrer son propre type sans
     * forker le package (MapLibreMapInstance.registerLayerRenderer('custom', fn)).
     */
    static layerRenderers = new Map([
        ['tile', addTileLayer],
        ['geojson', addGeoJsonLayer],
        ['points', addGeoJsonLayer],
        ['svg_overlay', addSvgOverlayLayer],
    ])

    static registerLayerRenderer(type, renderer) {
        if (!type || typeof renderer !== 'function') {
            throw new Error('Un renderer de couche requiert un type et une fonction.')
        }

        MapLibreMapInstance.layerRenderers.set(type, renderer)
    }

    constructor(element, payload) {
        this.element = element
        this.payload = payload
        this.map = null
        this.layers = new Map()
        this.activeStyleUrl = null
        this.handleMapClick = (event) => this.coordinatesPicked(event.lngLat)
    }

    initialStyle() {
        const styleLayer = (this.payload.layers ?? []).find(
            (layer) => layer.visible && (layer.options?.style_url ?? layer.options?.styleUrl),
        )
        const styleUrl = styleLayer ? (styleLayer.options.style_url ?? styleLayer.options.styleUrl) : null

        if (styleUrl) {
            this.activeStyleUrl = styleUrl

            return styleUrl
        }

        return DEFAULT_STYLE
    }

    // Les surcharges saisies dans un formulaire arrivent parfois en chaînes
    // ("2.00") : MapLibre les compare telles quelles et lève sinon
    // "maxZoom must be greater than or equal to minZoom".
    numberOrUndefined(value) {
        if (value === null || value === undefined || value === '') {
            return undefined
        }

        const number = Number(value)

        return Number.isFinite(number) ? number : undefined
    }

    mount() {
        const center = this.payload.map.center

        this.map = new maplibregl.Map({
            container: this.element,
            style: this.initialStyle(),
            center: [center.lng, center.lat],
            zoom: this.numberOrUndefined(this.payload.map.zoom),
            minZoom: this.numberOrUndefined(this.payload.map.minZoom),
            maxZoom: this.numberOrUndefined(this.payload.map.maxZoom),
            attributionControl: false,
            ...(this.payload.map.options ?? {}),
        })

        if (this.payload.controls?.zoom ?? true) {
            this.map.addControl(new maplibregl.NavigationControl(), 'top-left')
        }

        this.map.addControl(new maplibregl.AttributionControl({ compact: true }))

        this.map.on('load', () => {
            this.configureInteraction()
            this.renderLayers()
            this.renderPoints()
            this.renderLayerControl()

            if (this.payload.state?.fitBounds) {
                this.fitToVisibleContent()
            }

            this.focusSelectedPoint()
        })
    }

    update(payload) {
        const previousMap = this.payload.map
        this.payload = payload

        if (!this.map) {
            return
        }

        // Une mise à jour peut arriver avant la fin du chargement du style
        // initial (ex. un second appel rapproché) : addSource/addLayer y
        // lèvent "Style is not done loading" tant que ce n'est pas le cas.
        if (!this.map.isStyleLoaded()) {
            this.map.once('load', () => this.update(payload))
            return
        }

        if (
            previousMap.id !== payload.map.id
            || previousMap.center?.lat !== payload.map.center?.lat
            || previousMap.center?.lng !== payload.map.center?.lng
            || previousMap.zoom !== payload.map.zoom
        ) {
            this.map.jumpTo({ center: [payload.map.center.lng, payload.map.center.lat], zoom: payload.map.zoom })
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
        this.map?.remove()
        this.map = null
        this.layerControl = null
        this.layers.clear()
    }

    /** Bascule le style de base (mutuellement exclusif entre couches "style"). */
    activateStyle(url, onReady) {
        if (this.activeStyleUrl === url) {
            onReady?.()
            return
        }

        this.activeStyleUrl = url
        this.map.setStyle(url)
        this.map.once('style.load', () => {
            this.renderLayers()
            this.renderPoints()
            onReady?.()
        })
    }

    isActiveStyle(url) {
        return this.activeStyleUrl === url
    }

    renderLayers() {
        for (const layer of this.payload.layers ?? []) {
            const renderer = MapLibreMapInstance.layerRenderers.get(layer.type)
            let mapLayer = null

            if (renderer) {
                mapLayer = renderer(this.map, layer, {
                    mapId: this.payload.map.id,
                    scope: this.payload.state?.eventScope,
                    activateStyle: (url, onReady) => this.activateStyle(url, onReady),
                    isActiveStyle: (url) => this.isActiveStyle(url),
                })
            } else {
                console.warn(`[filament-map] Type de couche "${layer.type}" non pris en charge par le renderer MapLibre (couche "${layer.key}").`)
            }

            if (mapLayer) {
                this.layers.set(layer.key, mapLayer)

                mapLayer.on?.('filament-map:ready', () => {
                    if (
                        this.payload.state?.fitBounds
                        && this.layers.get(layer.key) === mapLayer
                    ) {
                        this.fitToVisibleContent()
                    }
                })

                mapLayer.on?.('filament-map:error', (event) => {
                    window.dispatchEvent(new CustomEvent('filament-map:layer-error', {
                        detail: {
                            mapId: this.payload.map.id,
                            scope: this.payload.state?.eventScope,
                            layer,
                            message: event?.error?.message ?? 'Impossible de charger la couche.',
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
            onPointHover: (point, hovering) => this.pointHovered(point, hovering),
        })

        if (markerLayer) {
            this.layers.set('__points', markerLayer)
        }
    }

    renderLayerControl() {
        if (!(this.payload.controls?.layers ?? true)) {
            return
        }

        const baseLayers = []
        const overlays = []

        for (const layer of this.payload.layers ?? []) {
            const mapLayer = this.layers.get(layer.key)

            if (!mapLayer) {
                continue
            }

            const entry = { key: layer.key, label: layer.name || layer.key, layer: mapLayer }

            if (layer.type === 'tile') {
                baseLayers.push(entry)
            } else {
                overlays.push(entry)
            }
        }

        if (this.layers.has('__points')) {
            overlays.push({ key: '__points', label: 'Points', layer: this.layers.get('__points') })
        }

        this.layerControl = { baseLayers, overlays }
        window.dispatchEvent(new CustomEvent('filament-map:layer-control', {
            detail: {
                mapId: this.payload.map.id,
                scope: this.payload.state?.eventScope,
                baseLayers: baseLayers.map(({ key, label }) => ({ key, label })),
                overlays: overlays.map(({ key, label }) => ({ key, label })),
            },
        }))
    }

    clearLayers() {
        for (const layer of this.layers.values()) {
            layer.destroy?.()
        }

        this.layers.clear()
    }

    fitToVisibleContent() {
        let west = Infinity
        let south = Infinity
        let east = -Infinity
        let north = -Infinity

        for (const layer of this.layers.values()) {
            if (!layer.filamentMapVisible) {
                continue
            }

            const bounds = layer.getBounds?.()

            if (!bounds) {
                continue
            }

            west = Math.min(west, bounds[0][0])
            south = Math.min(south, bounds[0][1])
            east = Math.max(east, bounds[1][0])
            north = Math.max(north, bounds[1][1])
        }

        if (Number.isFinite(west)) {
            this.map.fitBounds([[west, south], [east, north]], { padding: 24 })
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

        this.map.panTo(marker.getLngLat())
        marker.togglePopup?.()
    }

    acceptsCommand(detail = {}) {
        const sameMap = detail.mapId === null
            || detail.mapId === undefined
            || String(detail.mapId) === String(this.payload.map.id)
        const scope = this.payload.state?.eventScope
        const sameScope = !detail.scope || !scope || detail.scope === scope

        return sameMap && sameScope
    }

    setLayerVisibility(layerKey, command) {
        const layer = this.layers.get(String(layerKey))

        if (!layer) {
            return false
        }

        const visible = layer.filamentMapVisible
        const shouldShow = command === 'show-layer'
            || (command === 'toggle-layer' && !visible)

        if (shouldShow && !visible) {
            layer.addTo()
        }

        if (!shouldShow && visible) {
            layer.removeFrom()
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

    /** Le pointeur entre sur un point ou en sort : de quoi éclairer, ailleurs dans la page, ce qui le concerne. */
    pointHovered(point, hovering) {
        window.dispatchEvent(new CustomEvent('filament-map:point-hovered', {
            detail: this.eventDetail({ point, hovering }),
        }))
    }

    eventDetail(detail = {}) {
        return {
            mapId: this.payload.map.id,
            scope: this.payload.state?.eventScope,
            ...detail,
        }
    }
}
