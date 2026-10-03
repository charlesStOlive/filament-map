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
        // info | loading | ok | error : la couleur du message sous la carte.
        status: 'info',
        fallbackStyle: null,
        // Le fond par défaut est chargé : on peut y poser une couche. Un style prévisualisé le remplace tout entier.
        baseReady: false,
        // Chaque aperçu a son numéro : un événement d'un aperçu précédent ne change pas le message.
        attempt: 0,
        refreshTimer: null,

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
                this.map.on('error', (event) => this.loadFailed(event))
                this.map.on('load', () => {
                    this.baseReady = true
                    this.refresh()
                })
                this.watchFields()
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

        /** L'aperçu suit le formulaire : un champ de la couche change, il se refait. */
        watchFields() {
            const paths = Object.entries(config.fields)
                .filter(([name]) => name !== 'visible')
                .map(([, path]) => path)

            for (const path of paths) {
                try {
                    this.$wire.$watch(path, () => this.scheduleRefresh())
                } catch (error) {
                    // Sans $watch (ancienne version de Livewire), le bouton « Actualiser l'aperçu » reste là.
                }
            }
        },

        scheduleRefresh() {
            window.clearTimeout(this.refreshTimer)
            this.refreshTimer = window.setTimeout(() => this.refresh(), 300)
        },

        refresh() {
            if (!this.map || !this.baseReady) {
                return
            }

            // Un style prévisualisé a remplacé le fond par défaut : on le remet avant de poser autre chose.
            if (this.previewLayer?.type === 'style') {
                this.previewLayer = null
                this.baseReady = false
                this.map.setStyle(this.fallbackStyle, { diff: false })
                this.map.once('style.load', () => {
                    this.baseReady = true
                    this.refresh()
                })
                return
            }

            this.clearPreviewLayer()
            this.attempt++

            const layer = this.layerState()

            if (layer.visible === false) {
                this.setMessage('Couche masquée par défaut.')
                return
            }

            if (layer.type === 'style' || (layer.type === 'tile' && (layer.options?.style_url ?? layer.options?.styleUrl))) {
                this.previewStyle(layer)
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

            this.setMessage(`Aperçu non disponible pour le type ${layer.type || 'inconnu'}.`)
        },

        setMessage(message, status = 'info') {
            this.message = message
            this.status = status
        },

        /** La couche est posée : elle est chargée quand la carte a fini de travailler sans erreur. */
        awaitLoaded(message) {
            const attempt = this.attempt
            this.setMessage('Chargement…', 'loading')
            this.map.once('idle', () => {
                if (attempt === this.attempt && this.status === 'loading') {
                    this.setMessage(message, 'ok')
                }
            })
        },

        /** Une erreur de MapLibre : la source refuse, n'existe pas, ou ne se lit pas. */
        loadFailed(event) {
            const error = event?.error ?? {}
            const status = error.status
            let host = ''

            try {
                host = error.url ? ` (${new URL(error.url, window.location.href).host})` : ''
            } catch (exception) {
                host = ''
            }

            const reason = status === 401 || status === 403
                ? `Accès refusé (${status})${host} : clé absente, invalide ou limitée à d’autres domaines, ou carte non publique.`
                : status === 404
                    ? `Introuvable (404)${host} : vérifie l’URL.`
                    : status
                        ? `Le serveur répond ${status}${host}.`
                        : `Erreur de chargement : ${error.message ?? 'inconnue'}${host}.`

            this.setMessage(reason, 'error')
        },

        layerState() {
            return {
                type: this.getField(config.fields.type),
                sourceType: this.getField(config.fields.sourceType),
                sourceUrl: this.resolveKeys(this.getField(config.fields.sourceUrl)),
                sourcePath: this.getField(config.fields.sourcePath),
                sourceJson: this.parseJson(this.getField(config.fields.sourceJson), null),
                style: this.parseJson(this.getField(config.fields.style), {}),
                styleRules: this.parseJson(this.getField(config.fields.styleRules), []),
                options: this.resolveKeys(this.parseJson(this.getField(config.fields.options), {})),
                visible: this.getField(config.fields.visible),
            }
        },

        /** « {key:maptiler} » → la clé configurée, comme le fait le serveur pour les cartes (Support\MapKeys). */
        resolveKeys(value) {
            if (typeof value === 'string') {
                return value.replace(/\{key:([a-z0-9_-]+)\}/gi, (match, name) => config.keys?.[name.toLowerCase()] ?? '')
            }

            if (Array.isArray(value)) {
                return value.map((item) => this.resolveKeys(item))
            }

            if (value && typeof value === 'object') {
                return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, this.resolveKeys(item)]))
            }

            return value
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

        previewStyle(layer) {
            const url = layer.type === 'tile'
                ? (layer.options?.style_url ?? layer.options?.styleUrl)
                : (this.textUrl(layer.sourceUrl) ?? layer.options?.style_url ?? layer.options?.styleUrl)

            if (!url) {
                this.setMessage('Ajoute l’URL du style (style.json) pour prévisualiser ce fond.')
                return
            }

            const attempt = this.attempt
            this.previewLayer = { type: 'style' }
            this.setMessage('Chargement du style…', 'loading')
            this.map.setStyle(url, { diff: false })
            this.map.once('style.load', () => {
                if (attempt !== this.attempt) {
                    return
                }

                const name = this.map.getStyle()?.name
                this.awaitLoaded(name ? `Style « ${name} » chargé.` : 'Style chargé.')
            })
        },

        previewTile(layer) {
            const url = this.sourceUrl(layer)

            if (!url) {
                this.setMessage('Ajoute une URL de tuiles pour prévisualiser ce fond.')
                return
            }

            if (/style\.json(\?|$)/.test(url)) {
                this.setMessage('Cette adresse est un style (style.json), pas des tuiles : choisis le type « Fond de carte vectoriel ».', 'error')
                return
            }

            const id = 'filament-map-preview-tile'
            this.map.addSource(id, { type: 'raster', tiles: [url], tileSize: 256 })
            this.map.addLayer({ id, type: 'raster', source: id })
            this.previewLayer = { type: 'raster', ids: [id] }
            this.awaitLoaded('Tuiles chargées.')
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
                this.awaitLoaded('GeoJSON chargé.')
                this.map.once('idle', () => this.fitLayer())
            }

            if (layer.sourceType === 'json') {
                if (!layer.sourceJson) {
                    this.setMessage('Ajoute un GeoJSON dans « Source JSON ».')
                    return
                }

                createLayer(layer.sourceJson)
                return
            }

            const url = this.sourceUrl(layer)

            if (!url) {
                this.setMessage(layer.sourceType === 'file' ? 'Enregistre la couche après le téléversement pour prévisualiser le fichier.' : 'Ajoute une URL ou un fichier GeoJSON.')
                return
            }

            createLayer(url)
        },

        previewSvgOverlay(layer) {
            const url = this.sourceUrl(layer)
            const bounds = layer.options?.bounds ?? this.currentMapConfig().bounds

            if (!url || !bounds?.southWest || !bounds?.northEast) {
                this.setMessage('Ajoute une source SVG et son emprise (options : bounds).')
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
            this.awaitLoaded('Image SVG chargée.')
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

            for (const id of this.previewLayer.ids ?? []) {
                if (this.map.getLayer(id)) {
                    this.map.removeLayer(id)
                }
            }

            const sourceId = this.previewLayer.sourceId ?? this.previewLayer.ids?.[0]

            if (sourceId && this.map.getSource(sourceId)) {
                this.map.removeSource(sourceId)
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
                this.setMessage(`JSON invalide : ${error.message}`, 'error')
                return fallback
            }
        },
    }
}
