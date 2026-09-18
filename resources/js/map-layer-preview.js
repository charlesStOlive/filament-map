const FALLBACK_STYLE = (tiles) => ({
    version: 8,
    sources: {
        'filament-map-preview-fallback': {
            type: 'raster',
            tiles: [tiles.url],
            tileSize: 256,
            attribution: tiles.attribution ?? undefined,
        },
    },
    layers: [{ id: 'filament-map-preview-fallback', type: 'raster', source: 'filament-map-preview-fallback' }],
})

window.filamentMapLayerPreview = function filamentMapLayerPreview(config) {
    return {
        map: null,
        previewLayer: null,
        message: '',
        fallbackStyle: null,

        init() {
            const boot = () => {
                const element = document.getElementById(config.id)

                if (!element || !window.maplibregl || this.map) {
                    return
                }

                const mapConfig = this.currentMapConfig()
                this.fallbackStyle = FALLBACK_STYLE(config.tiles)

                this.map = new maplibregl.Map({
                    container: element,
                    style: this.fallbackStyle,
                    center: [mapConfig.center.lng, mapConfig.center.lat],
                    zoom: mapConfig.zoom,
                })
                this.map.addControl(new maplibregl.NavigationControl(), 'top-left')
                this.map.on('load', () => this.refresh())
            }

            if (window.maplibregl) {
                boot()
                return
            }

            const interval = window.setInterval(() => {
                if (window.maplibregl) {
                    window.clearInterval(interval)
                    boot()
                }
            }, 50)
        },

        getField(path) {
            return this.$wire.get(path)
        },

        currentMapConfig() {
            const mapId = this.getField(config.fields.map)

            return config.maps?.[mapId] ?? {
                center: { lat: config.defaults.lat, lng: config.defaults.lng },
                zoom: config.defaults.zoom,
                bounds: null,
            }
        },

        refresh() {
            if (!this.map || !this.map.isStyleLoaded()) {
                return
            }

            this.clearPreviewLayer()

            const layer = this.layerState()

            if (layer.visible === false) {
                this.message = 'Couche masquée par défaut.'
                return
            }

            if (layer.type === 'tile') {
                this.previewTile(layer)
                return
            }

            if (layer.type === 'geojson' || layer.type === 'points') {
                this.previewGeoJson(layer)
                return
            }

            if (layer.type === 'svg_overlay') {
                this.previewSvgOverlay(layer)
                return
            }

            this.message = `Preview non disponible pour le type ${layer.type || 'inconnu'}.`
        },

        layerState() {
            return {
                type: this.getField(config.fields.type),
                sourceType: this.getField(config.fields.sourceType),
                sourceUrl: this.getField(config.fields.sourceUrl),
                sourcePath: this.getField(config.fields.sourcePath),
                sourceJson: this.parseJson(this.getField(config.fields.sourceJson), null),
                style: this.parseJson(this.getField(config.fields.style), {}),
                styleRules: this.parseJson(this.getField(config.fields.styleRules), []),
                options: this.parseJson(this.getField(config.fields.options), {}),
                visible: this.getField(config.fields.visible),
            }
        },

        sourceUrl(layer) {
            if (layer.sourceType === 'file' || layer.sourceType === 'path') {
                return this.publicFileUrl(layer.sourcePath)
            }

            return this.textUrl(layer.sourceUrl)
        },

        textUrl(value) {
            return typeof value === 'string' && value.trim() !== '' ? value : null
        },

        publicFileUrl(value) {
            const path = this.extractStoredPath(value)

            if (!path) {
                return null
            }

            if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
                return path
            }

            return `${config.files?.urlPrefix ?? '/storage/'}${path.replace(/^\/+/, '')}`
        },

        extractStoredPath(value) {
            if (typeof value === 'string') {
                return value.trim() === '' ? null : value
            }

            if (Array.isArray(value)) {
                for (const item of value) {
                    const path = this.extractStoredPath(item)

                    if (path) {
                        return path
                    }
                }
            }

            if (value && typeof value === 'object') {
                for (const key of ['path', 'url', 'previewUrl', 'preview_url']) {
                    const path = this.extractStoredPath(value[key])

                    if (path) {
                        return path
                    }
                }

                for (const item of Object.values(value)) {
                    const path = this.extractStoredPath(item)

                    if (path) {
                        return path
                    }
                }
            }

            return null
        },

        previewTile(layer) {
            const url = this.sourceUrl(layer)

            if (!url) {
                this.message = 'Ajoute une URL de tuiles pour prévisualiser ce fond.'
                return
            }

            const styleUrl = layer.options?.style_url ?? layer.options?.styleUrl

            if (styleUrl) {
                this.map.setStyle(styleUrl)
                this.previewLayer = { type: 'style' }
                this.message = 'Style prévisualisé.'
                return
            }

            const id = 'filament-map-preview-tile'
            this.map.addSource(id, { type: 'raster', tiles: [url], tileSize: 256 })
            this.map.addLayer({ id, type: 'raster', source: id })
            this.previewLayer = { type: 'raster', ids: [id] }
            this.message = 'Fond de tuiles prévisualisé.'
        },

        previewGeoJson(layer) {
            const createLayer = (data) => {
                const sourceId = 'filament-map-preview-geojson'
                const fillId = `${sourceId}-fill`
                const lineId = `${sourceId}-line`
                const circleId = `${sourceId}-circle`

                this.map.addSource(sourceId, { type: 'geojson', data })
                this.map.addLayer({
                    id: fillId, type: 'fill', source: sourceId,
                    filter: ['==', ['geometry-type'], 'Polygon'],
                    paint: { 'fill-color': layer.style?.fillColor ?? '#3388ff', 'fill-opacity': layer.style?.fillOpacity ?? 0.2 },
                })
                this.map.addLayer({
                    id: lineId, type: 'line', source: sourceId,
                    filter: ['in', ['geometry-type'], ['literal', ['LineString', 'Polygon']]],
                    paint: { 'line-color': layer.style?.color ?? '#3388ff', 'line-width': layer.style?.weight ?? 3 },
                })
                this.map.addLayer({
                    id: circleId, type: 'circle', source: sourceId,
                    filter: ['==', ['geometry-type'], 'Point'],
                    paint: { 'circle-color': layer.style?.fillColor ?? layer.style?.color ?? '#3388ff', 'circle-radius': 6 },
                })

                this.previewLayer = { type: 'geojson', ids: [fillId, lineId, circleId], sourceId }
                this.fitLayer()
                this.message = 'GeoJSON prévisualisé.'
            }

            if (layer.sourceType === 'json') {
                if (!layer.sourceJson) {
                    this.message = 'Ajoute un GeoJSON dans source_json.'
                    return
                }

                createLayer(layer.sourceJson)
                return
            }

            const url = this.sourceUrl(layer)

            if (!url) {
                this.message = layer.sourceType === 'file' ? 'Enregistre le layer après upload pour prévisualiser le fichier.' : 'Ajoute une URL ou un fichier GeoJSON.'
                return
            }

            createLayer(url)
        },

        previewSvgOverlay(layer) {
            const url = this.sourceUrl(layer)
            const bounds = layer.options?.bounds ?? this.currentMapConfig().bounds

            if (!url || !bounds?.southWest || !bounds?.northEast) {
                this.message = 'Ajoute une source SVG et des bounds.'
                return
            }

            const id = 'filament-map-preview-svg'
            const coordinates = [
                [bounds.southWest.lng, bounds.northEast.lat],
                [bounds.northEast.lng, bounds.northEast.lat],
                [bounds.northEast.lng, bounds.southWest.lat],
                [bounds.southWest.lng, bounds.southWest.lat],
            ]

            this.map.addSource(id, { type: 'image', url, coordinates })
            this.map.addLayer({ id, type: 'raster', source: id })
            this.previewLayer = {
                type: 'svg',
                ids: [id],
                bounds: [[bounds.southWest.lng, bounds.southWest.lat], [bounds.northEast.lng, bounds.northEast.lat]],
            }

            this.fitLayer()
            this.message = 'SVG overlay prévisualisé.'
        },

        fitLayer() {
            if (!this.previewLayer) {
                return
            }

            if (this.previewLayer.bounds) {
                this.map.fitBounds(this.previewLayer.bounds, { padding: 24 })
                return
            }

            if (this.previewLayer.sourceId) {
                const features = this.map.querySourceFeatures(this.previewLayer.sourceId)
                const bounds = this.boundsOf(features)

                if (bounds) {
                    this.map.fitBounds(bounds, { padding: 24 })
                }
            }
        },

        boundsOf(features) {
            let west = Infinity
            let south = Infinity
            let east = -Infinity
            let north = -Infinity

            const visit = (coords) => {
                if (typeof coords[0] === 'number') {
                    const [lng, lat] = coords
                    west = Math.min(west, lng)
                    east = Math.max(east, lng)
                    south = Math.min(south, lat)
                    north = Math.max(north, lat)
                    return
                }

                coords.forEach(visit)
            }

            for (const feature of features) {
                if (feature.geometry?.coordinates) {
                    visit(feature.geometry.coordinates)
                }
            }

            return Number.isFinite(west) ? [[west, south], [east, north]] : null
        },

        clearPreviewLayer() {
            if (!this.previewLayer) {
                return
            }

            if (this.previewLayer.type === 'style') {
                this.map.setStyle(this.fallbackStyle)
            } else {
                for (const id of this.previewLayer.ids ?? []) {
                    if (this.map.getLayer(id)) {
                        this.map.removeLayer(id)
                    }
                }

                const sourceId = this.previewLayer.sourceId ?? this.previewLayer.ids?.[0]

                if (sourceId && this.map.getSource(sourceId)) {
                    this.map.removeSource(sourceId)
                }
            }

            this.previewLayer = null
        },

        parseJson(value, fallback) {
            if (value === null || value === undefined || value === '') {
                return fallback
            }

            if (typeof value === 'object') {
                return value
            }

            try {
                return JSON.parse(value)
            } catch (error) {
                this.message = `JSON invalide : ${error.message}`
                return fallback
            }
        },
    }
}
