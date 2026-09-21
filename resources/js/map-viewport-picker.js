const FALLBACK_STYLE = (tiles) => ({
    version: 8,
    sources: {
        'filament-map-picker-fallback': {
            type: 'raster',
            tiles: [tiles.url],
            tileSize: 256,
            attribution: tiles.attribution ?? undefined,
        },
    },
    layers: [{ id: 'filament-map-picker-fallback', type: 'raster', source: 'filament-map-picker-fallback' }],
})

window.filamentMapViewportPicker = function filamentMapViewportPicker(config) {
    return {
        map: null,
        marker: null,
        summary: '',
        coordinatesPickedHandler: null,
        resizeObserver: null,
        hasFittedVisibleContent: false,

        // Recherche d'adresse et plein écran.
        query: '',
        results: [],
        searching: false,
        searched: false,
        message: '',
        fullscreen: false,
        fullscreenHandler: null,
        closeModalHandler: null,

        // Dans le mode « repère + zoom », le zoom n'est enregistré qu'une fois la carte posée : ses premiers réglages ne comptent pas.
        settled: false,

        init() {
            this.fullscreenHandler = () => {
                this.fullscreen = document.fullscreenElement !== null && document.fullscreenElement.contains(this.$root)
            }
            document.addEventListener('fullscreenchange', this.fullscreenHandler)

            // Fermer le popup (« Terminé », la croix) pendant qu'il est en plein écran : on en sort, sans quoi l'écran resterait noir.
            this.closeModalHandler = () => {
                if (document.fullscreenElement?.contains(this.$root)) {
                    document.exitFullscreen()
                }
            }
            window.addEventListener('close-modal', this.closeModalHandler)

            const boot = () => {
                const element = document.getElementById(config.id)

                if (!element || !window.maplibregl || this.map || (config.mapPayload && !window.FilamentMap)) {
                    return
                }

                const payloadCenter = config.mapPayload?.map?.center ?? {}
                const initialCoordinates = this.normalizeCoordinates(
                    this.getField(config.latitudePath) ?? payloadCenter.lat ?? config.defaults.lat,
                    this.getField(config.longitudePath) ?? payloadCenter.lng ?? config.defaults.lng,
                )
                const lat = initialCoordinates.lat
                const lng = initialCoordinates.lng
                const zoom = Number(this.getField(config.zoomPath) ?? config.mapPayload?.map?.zoom ?? config.defaults.zoom)

                if (config.mapPayload) {
                    // Un sélecteur de vue reprend le zoom déjà saisi dans le
                    // formulaire (ex. celui propre à un voyage) plutôt que
                    // celui de la scène.
                    const fieldZoom = config.type !== 'coordinate' ? this.getField(config.zoomPath) : null
                    const startZoom = fieldZoom !== null && fieldZoom !== undefined && fieldZoom !== '' && Number.isFinite(Number(fieldZoom))
                        ? Number(fieldZoom)
                        : config.mapPayload.map.zoom

                    const payload = {
                        ...config.mapPayload,
                        map: {
                            ...config.mapPayload.map,
                            center: { lat, lng },
                            zoom: startZoom,
                        },
                        state: {
                            ...(config.mapPayload.state ?? {}),
                            eventScope: config.scope,
                            fitBounds: config.type === 'coordinate',
                            interactive: true,
                        },
                    }

                    window.FilamentMap.init(config.id, payload)
                    this.map = window.FilamentMap.instances.get(config.id)?.map ?? null
                } else {
                    this.map = new maplibregl.Map({
                        container: element,
                        style: FALLBACK_STYLE(config.tiles),
                        center: [lng, lat],
                        zoom,
                    })
                    this.map.addControl(new maplibregl.NavigationControl(), 'top-left')
                }

                if (!this.map) {
                    return
                }

                this.marker = new maplibregl.Marker({ draggable: true }).setLngLat([lng, lat]).addTo(this.map)
                this.marker.on('dragend', () => this.syncFromMarker())

                if (config.mapPayload) {
                    this.coordinatesPickedHandler = (event) => {
                        if (event.detail?.scope !== config.scope) {
                            return
                        }

                        this.selectCoordinates(event.detail.lat, event.detail.lng)
                    }
                    window.addEventListener('filament-map:coordinates-picked', this.coordinatesPickedHandler)
                } else {
                    this.map.on('click', (event) => this.selectCoordinates(event.lngLat.lat, event.lngLat.lng))
                }

                if (config.type === 'viewport') {
                    this.map.on('moveend', () => this.syncFromMap())
                    this.map.on('zoomend', () => this.syncFromMap())
                } else if (config.type === 'marker-zoom') {
                    // La position est celle du repère : déplacer la carte n'y touche pas. Seul le zoom affiché est enregistré.
                    this.map.once('idle', () => { this.settled = true })
                    this.map.on('moveend', () => this.syncSummary())
                    this.map.on('zoomend', () => this.syncZoom())
                } else {
                    this.map.on('moveend', () => this.syncSummary())
                    this.map.on('zoomend', () => this.syncSummary())
                }

                this.observeSize(element)
                this.syncSummary()
            }

            if (window.maplibregl && (!config.mapPayload || window.FilamentMap)) {
                boot()
                return
            }

            const interval = window.setInterval(() => {
                if (window.maplibregl && (!config.mapPayload || window.FilamentMap)) {
                    window.clearInterval(interval)
                    boot()
                }
            }, 50)
        },

        destroy() {
            this.resizeObserver?.disconnect()

            if (this.fullscreenHandler) {
                document.removeEventListener('fullscreenchange', this.fullscreenHandler)
            }

            if (this.closeModalHandler) {
                window.removeEventListener('close-modal', this.closeModalHandler)
            }

            if (this.coordinatesPickedHandler) {
                window.removeEventListener('filament-map:coordinates-picked', this.coordinatesPickedHandler)
            }

            if (config.mapPayload) {
                window.FilamentMap?.destroy(config.id)
            } else {
                this.map?.remove()
            }
        },

        observeSize(element) {
            this.resizeObserver = new ResizeObserver((entries) => {
                const size = entries[0]?.contentRect

                if (!size || size.width <= 0 || size.height <= 0) {
                    return
                }

                this.map.resize()

                if (config.type === 'coordinate' && config.mapPayload && !this.hasFittedVisibleContent) {
                    this.hasFittedVisibleContent = true
                    window.FilamentMap.instances.get(config.id)?.fitToVisibleContent()
                }
            })
            this.resizeObserver.observe(element)
        },

        /**
         * Plein écran : la fenêtre du popup qui contient le sélecteur, sinon le sélecteur lui-même. C'est le plein écran du
         * navigateur (Échap en sort) ; la carte se redimensionne d'elle-même (voir observeSize).
         */
        toggleFullscreen() {
            if (document.fullscreenElement) {
                document.exitFullscreen()

                return
            }

            const target = this.$root.closest('.fi-modal-window') ?? this.$root

            target.requestFullscreen?.().catch(() => {})
        },

        /**
         * Cherche un lieu (le serveur interroge le service d'adresses : voir MapViewportPicker::searchAddress). Une recherche à
         * la demande — bouton ou Entrée —, jamais à chaque frappe : le service public l'interdit.
         */
        async search() {
            const query = this.query.trim()

            if (!config.search || query.length < 3 || this.searching) {
                return
            }

            this.searching = true
            this.searched = true
            this.results = []
            this.message = 'Recherche…'

            try {
                const response = await this.$wire.callSchemaComponentMethod(config.componentKey, 'searchAddress', { query })

                this.results = response?.results ?? []
                this.message = response?.error ?? (this.results.length === 0 ? 'Aucun lieu trouvé.' : '')
            } catch (error) {
                this.message = 'La recherche a échoué. Réessayez.'
            } finally {
                this.searching = false
            }
        },

        /** Va au lieu choisi : la carte s'y cale (sur son cadre quand on le connaît), et le repère s'y pose. */
        pick(result) {
            const bounds = result.bounds

            if (bounds) {
                this.map.fitBounds([[bounds.west, bounds.south], [bounds.east, bounds.north]], { padding: 40, maxZoom: 17, duration: 600 })
            } else {
                this.map.flyTo({ center: [result.lng, result.lat], zoom: 14, duration: 600 })
            }

            // Le repère se pose sur le lieu (sauf pour une vue à cadrer par son seul centre, où la carte suit).
            if (config.type !== 'viewport') {
                this.selectCoordinates(result.lat, result.lng)
            }

            this.results = []
            this.searched = false
            this.message = ''
        },

        getField(path) {
            return this.$wire.get(path)
        },

        setField(path, value) {
            this.$wire.set(path, value, false)
        },

        /** Le zoom de la carte, arrondi au centième : un zoom fractionnaire à 15 décimales n'a aucun sens (et échoue à la validation). */
        roundZoom(zoom) {
            return Math.round(Number(zoom) * 100) / 100
        },

        syncZoom() {
            if (this.settled) {
                this.setField(config.zoomPath, this.roundZoom(this.map.getZoom()))
            }

            this.syncSummary()
        },

        /** Ramène la vue sur le repère (la carte a pu être déplacée pour regarder ailleurs). */
        centerOnMarker() {
            this.map.easeTo({ center: this.marker.getLngLat(), duration: 400 })
        },

        syncFromMarker() {
            const position = this.marker.getLngLat()
            const coordinates = this.normalizeCoordinates(position.lat, position.lng)

            this.marker.setLngLat([coordinates.lng, coordinates.lat])
            this.setField(config.latitudePath, coordinates.lat)
            this.setField(config.longitudePath, coordinates.lng)

            if (config.type === 'viewport') {
                this.syncFromMap(false)
            } else {
                this.syncSummary()
            }
        },

        selectCoordinates(lat, lng) {
            const coordinates = this.normalizeCoordinates(lat, lng)

            this.marker.setLngLat([coordinates.lng, coordinates.lat])
            this.setField(config.latitudePath, coordinates.lat)
            this.setField(config.longitudePath, coordinates.lng)
            this.syncSummary()
        },

        normalizeCoordinates(lat, lng) {
            const latitude = Math.max(-90, Math.min(90, Number(lat)))
            const longitude = ((Number(lng) + 180) % 360 + 360) % 360 - 180

            return {
                lat: Number(latitude.toFixed(7)),
                lng: Number(longitude.toFixed(7)),
            }
        },

        syncFromMap(syncCenter = true) {
            const center = this.map.getCenter()
            const coordinates = this.normalizeCoordinates(center.lat, center.lng)

            if (syncCenter) {
                this.marker.setLngLat([coordinates.lng, coordinates.lat])
                this.setField(config.latitudePath, coordinates.lat)
                this.setField(config.longitudePath, coordinates.lng)
            }

            this.setField(config.zoomPath, this.roundZoom(this.map.getZoom()))

            if (config.syncBounds) {
                const bounds = this.map.getBounds()
                const worldOffset = center.lng - coordinates.lng
                const payload = {
                    southWest: {
                        lat: Number(bounds.getSouthWest().lat.toFixed(7)),
                        lng: Number((bounds.getSouthWest().lng - worldOffset).toFixed(7)),
                    },
                    northEast: {
                        lat: Number(bounds.getNorthEast().lat.toFixed(7)),
                        lng: Number((bounds.getNorthEast().lng - worldOffset).toFixed(7)),
                    },
                }

                this.setField(config.boundsPath, JSON.stringify(payload, null, 2))
            }

            this.syncSummary()
        },

        fitConfiguredBounds() {
            const bounds = this.parseBounds(this.getField(config.boundsPath))

            if (!bounds?.southWest || !bounds?.northEast) {
                return
            }

            this.map.fitBounds([
                [bounds.southWest.lng, bounds.southWest.lat],
                [bounds.northEast.lng, bounds.northEast.lat],
            ], { padding: 24 })
        },

        parseBounds(value) {
            if (!value) {
                return null
            }

            if (typeof value === 'object') {
                return value
            }

            try {
                return JSON.parse(value)
            } catch (error) {
                return null
            }
        },

        syncSummary() {
            if (!this.map) {
                this.summary = ''
                return
            }

            if (config.type === 'marker-zoom' && this.marker) {
                const position = this.marker.getLngLat()
                this.summary = `Repère ${position.lat.toFixed(5)}, ${position.lng.toFixed(5)} - zoom ${this.roundZoom(this.map.getZoom()).toFixed(2)} (le carnet s'ouvre centré sur le repère, à ce zoom)`
                return
            }

            const center = this.map.getCenter()
            const coordinates = this.normalizeCoordinates(center.lat, center.lng)
            this.summary = `Centre ${coordinates.lat.toFixed(5)}, ${coordinates.lng.toFixed(5)} - zoom ${this.map.getZoom().toFixed(2)}`
        },
    }
}
