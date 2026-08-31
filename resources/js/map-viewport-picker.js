window.filamentMapViewportPicker = function filamentMapViewportPicker(config) {
    return {
        map: null,
        marker: null,
        summary: '',
        coordinatesPickedHandler: null,
        resizeObserver: null,
        hasFittedVisibleContent: false,

        init() {
            const boot = () => {
                const element = document.getElementById(config.id)

                if (!element || !window.L || this.map || (config.mapPayload && !window.FilamentMap)) {
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
                    const payload = {
                        ...config.mapPayload,
                        map: {
                            ...config.mapPayload.map,
                            center: { lat, lng },
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
                    this.map = L.map(element, {
                        zoomControl: true,
                    }).setView([lat, lng], zoom)

                    if (config.tiles.url) {
                        L.tileLayer(config.tiles.url, {
                            attribution: config.tiles.attribution ?? undefined,
                        }).addTo(this.map)
                    }
                }

                if (!this.map) {
                    return
                }

                this.marker = L.marker([lat, lng], {
                    draggable: true,
                }).addTo(this.map)

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
                    this.map.on('click', (event) => this.selectCoordinates(event.latlng.lat, event.latlng.lng))
                }

                if (config.type === 'viewport') {
                    this.map.on('moveend zoomend', () => this.syncFromMap())
                } else {
                    this.map.on('moveend zoomend', () => this.syncSummary())
                }

                this.observeSize(element)
                this.syncSummary()
            }

            if (window.L && (!config.mapPayload || window.FilamentMap)) {
                boot()
                return
            }

            const interval = window.setInterval(() => {
                if (window.L && (!config.mapPayload || window.FilamentMap)) {
                    window.clearInterval(interval)
                    boot()
                }
            }, 50)
        },

        destroy() {
            this.resizeObserver?.disconnect()

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

                this.map.invalidateSize(false)

                if (config.type === 'coordinate' && config.mapPayload && !this.hasFittedVisibleContent) {
                    this.hasFittedVisibleContent = true
                    window.FilamentMap.instances.get(config.id)?.fitToVisibleContent()
                }
            })
            this.resizeObserver.observe(element)
        },

        getField(path) {
            return this.$wire.get(path)
        },

        setField(path, value) {
            this.$wire.set(path, value, false)
        },

        syncFromMarker() {
            const position = this.marker.getLatLng()
            const coordinates = this.normalizeCoordinates(position.lat, position.lng)

            this.marker.setLatLng([coordinates.lat, coordinates.lng])
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

            this.marker.setLatLng([coordinates.lat, coordinates.lng])
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
                this.marker.setLatLng([coordinates.lat, coordinates.lng])
                this.setField(config.latitudePath, coordinates.lat)
                this.setField(config.longitudePath, coordinates.lng)
            }

            this.setField(config.zoomPath, this.map.getZoom())

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
                [bounds.southWest.lat, bounds.southWest.lng],
                [bounds.northEast.lat, bounds.northEast.lng],
            ], { padding: [24, 24] })
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

            const center = this.map.getCenter()
            const coordinates = this.normalizeCoordinates(center.lat, center.lng)
            this.summary = `Centre ${coordinates.lat.toFixed(5)}, ${coordinates.lng.toFixed(5)} - zoom ${this.map.getZoom()}`
        },
    }
}
