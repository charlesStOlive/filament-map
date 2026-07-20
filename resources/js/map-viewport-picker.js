window.filamentMapViewportPicker = function filamentMapViewportPicker(config) {
    return {
        map: null,
        marker: null,
        summary: '',

        init() {
            const boot = () => {
                const element = document.getElementById(config.id)

                if (!element || !window.L || this.map) {
                    return
                }

                const lat = Number(this.getField(config.latitudePath) ?? config.defaults.lat)
                const lng = Number(this.getField(config.longitudePath) ?? config.defaults.lng)
                const zoom = Number(this.getField(config.zoomPath) ?? config.defaults.zoom)

                this.map = L.map(element, {
                    zoomControl: true,
                }).setView([lat, lng], zoom)

                if (config.tiles.url) {
                    L.tileLayer(config.tiles.url, {
                        attribution: config.tiles.attribution ?? undefined,
                    }).addTo(this.map)
                }

                this.marker = L.marker([lat, lng], {
                    draggable: true,
                }).addTo(this.map)

                this.marker.on('dragend', () => this.syncFromMarker())
                this.map.on('moveend zoomend', () => this.syncFromMap())
                this.map.on('click', (event) => {
                    this.marker.setLatLng(event.latlng)
                    this.map.panTo(event.latlng)
                    this.syncFromMarker()
                })

                this.fitConfiguredBounds()
                this.syncSummary()
            }

            if (window.L) {
                boot()
                return
            }

            const interval = window.setInterval(() => {
                if (window.L) {
                    window.clearInterval(interval)
                    boot()
                }
            }, 50)
        },

        getField(path) {
            return this.$wire.get(path)
        },

        setField(path, value) {
            this.$wire.set(path, value, false)
        },

        syncFromMarker() {
            const position = this.marker.getLatLng()

            this.setField(config.latitudePath, Number(position.lat.toFixed(7)))
            this.setField(config.longitudePath, Number(position.lng.toFixed(7)))
            this.syncFromMap(false)
        },

        syncFromMap(syncCenter = true) {
            const center = this.map.getCenter()

            if (syncCenter) {
                this.marker.setLatLng(center)
                this.setField(config.latitudePath, Number(center.lat.toFixed(7)))
                this.setField(config.longitudePath, Number(center.lng.toFixed(7)))
            }

            this.setField(config.zoomPath, this.map.getZoom())

            if (config.syncBounds) {
                const bounds = this.map.getBounds()

                this.setField(config.boundsPath, {
                    southWest: {
                        lat: Number(bounds.getSouthWest().lat.toFixed(7)),
                        lng: Number(bounds.getSouthWest().lng.toFixed(7)),
                    },
                    northEast: {
                        lat: Number(bounds.getNorthEast().lat.toFixed(7)),
                        lng: Number(bounds.getNorthEast().lng.toFixed(7)),
                    },
                })
            }

            this.syncSummary()
        },

        fitConfiguredBounds() {
            const bounds = this.getField(config.boundsPath)

            if (!bounds?.southWest || !bounds?.northEast) {
                return
            }

            this.map.fitBounds([
                [bounds.southWest.lat, bounds.southWest.lng],
                [bounds.northEast.lat, bounds.northEast.lng],
            ], { padding: [24, 24] })
        },

        syncSummary() {
            if (!this.map) {
                this.summary = ''
                return
            }

            const center = this.map.getCenter()
            this.summary = `Centre ${center.lat.toFixed(5)}, ${center.lng.toFixed(5)} - zoom ${this.map.getZoom()}`
        },
    }
}
