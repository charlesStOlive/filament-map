window.filamentMapLayerPreview = function filamentMapLayerPreview(config) {
    return {
        map: null,
        baseLayer: null,
        previewLayer: null,
        message: '',

        init() {
            const boot = () => {
                const element = document.getElementById(config.id)

                if (!element || !window.L || this.map) {
                    return
                }

                const mapConfig = this.currentMapConfig()

                this.map = L.map(element, { zoomControl: true })
                    .setView([mapConfig.center.lat, mapConfig.center.lng], mapConfig.zoom)

                this.baseLayer = L.tileLayer(config.tiles.url, {
                    attribution: config.tiles.attribution ?? undefined,
                    maxZoom: 19,
                }).addTo(this.map)

                this.refresh()
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

        currentMapConfig() {
            const mapId = this.getField(config.fields.map)

            return config.maps?.[mapId] ?? {
                center: { lat: config.defaults.lat, lng: config.defaults.lng },
                zoom: config.defaults.zoom,
                bounds: null,
            }
        },

        refresh() {
            if (!this.map) {
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

            if (layer.type === 'geojson') {
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

            this.previewLayer = L.tileLayer(url, layer.options).addTo(this.map)
            this.message = 'Fond de tuiles prévisualisé.'
        },

        previewGeoJson(layer) {
            const createLayer = (data) => {
                this.previewLayer = L.geoJSON(data, {
                    style: (feature) => this.styleForFeature(feature, layer),
                    onEachFeature(feature, leafletLayer) {
                        const title = feature?.properties?.name ?? feature?.properties?.title

                        if (title) {
                            leafletLayer.bindTooltip(String(title))
                        }
                    },
                    ...layer.options,
                }).addTo(this.map)

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

            fetch(url)
                .then((response) => response.json())
                .then((data) => createLayer(data))
                .catch((error) => {
                    this.message = `Impossible de charger le GeoJSON : ${error.message}`
                })
        },

        previewSvgOverlay(layer) {
            const url = this.sourceUrl(layer)
            const bounds = layer.options?.bounds ?? this.currentMapConfig().bounds

            if (!url || !bounds?.southWest || !bounds?.northEast) {
                this.message = 'Ajoute une source SVG et des bounds.'
                return
            }

            this.previewLayer = L.imageOverlay(url, [
                [bounds.southWest.lat, bounds.southWest.lng],
                [bounds.northEast.lat, bounds.northEast.lng],
            ], layer.options).addTo(this.map)

            this.fitLayer()
            this.message = 'SVG overlay prévisualisé.'
        },

        styleForFeature(feature, layer) {
            const baseStyle = layer.style ?? {}

            for (const rule of layer.styleRules ?? []) {
                const property = rule?.when?.property
                const equals = rule?.when?.equals

                if (property && feature?.properties?.[property] === equals) {
                    return { ...baseStyle, ...(rule.style ?? {}) }
                }
            }

            return baseStyle
        },

        fitLayer() {
            if (!this.previewLayer?.getBounds) {
                return
            }

            const bounds = this.previewLayer.getBounds()

            if (bounds.isValid()) {
                this.map.fitBounds(bounds, { padding: [24, 24] })
            }
        },

        clearPreviewLayer() {
            if (this.previewLayer) {
                this.previewLayer.removeFrom(this.map)
                this.previewLayer = null
            }
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
