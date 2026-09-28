/**
 * MapPositionInput : le résumé d'une position dans la page, et le popup qui la modifie.
 *
 * Le popup travaille sur un brouillon (`draft`) : la position, le zoom… ne sont reportés dans le formulaire (`values`, les
 * champs liés à Livewire) qu'à « Valider ». La croix, Échap et « Annuler » abandonnent le brouillon ; à la réouverture, il
 * est refait d'après les valeurs enregistrées.
 */
const FALLBACK_STYLE = (tiles) => ({
    version: 8,
    sources: {
        'filament-map-position-fallback': {
            type: 'raster',
            tiles: [tiles.url],
            tileSize: 256,
            attribution: tiles.attribution ?? undefined,
        },
    },
    layers: [{ id: 'filament-map-position-fallback', type: 'raster', source: 'filament-map-position-fallback' }],
})

const blank = (value) => value === null || value === undefined || value === ''

const round = (value, decimals) => {
    const factor = 10 ** decimals

    return Math.round(Number(value) * factor) / factor
}

window.filamentMapPosition = function filamentMapPosition(config) {
    return {
        values: config.values,
        draft: { lat: null, lng: null, fields: {} },
        error: '',
        saving: false,

        map: null,
        marker: null,
        settled: false, // la carte s'est posée : avant, ses premiers réglages ne sont pas ceux de l'utilisateur
        resizeObserver: null,
        coordinatesPickedHandler: null,
        fullscreenHandler: null,
        closeModalHandler: null,

        query: '',
        results: [],
        searching: false,
        searched: false,
        message: '',
        fullscreen: false,

        init() {
            this.fullscreenHandler = () => {
                this.fullscreen = document.fullscreenElement !== null && document.fullscreenElement.contains(this.mapElement())
            }
            document.addEventListener('fullscreenchange', this.fullscreenHandler)

            // Fermer le popup pendant qu'il est en plein écran : on en sort, sans quoi l'écran resterait noir.
            this.closeModalHandler = (event) => {
                if (event.detail?.id === config.modalId && document.fullscreenElement?.contains(this.mapElement())) {
                    document.exitFullscreen()
                }
            }
            window.addEventListener('close-modal', this.closeModalHandler)

            if (config.hasMap) {
                this.waitForMap()
            }
        },

        destroy() {
            this.resizeObserver?.disconnect()
            document.removeEventListener('fullscreenchange', this.fullscreenHandler)
            window.removeEventListener('close-modal', this.closeModalHandler)

            if (this.coordinatesPickedHandler) {
                window.removeEventListener('filament-map:coordinates-picked', this.coordinatesPickedHandler)
            }

            if (this.map) {
                config.mapPayload ? window.FilamentMap?.destroy(config.id) : this.map.remove()
            }
        },

        mapElement() {
            return document.getElementById(config.id)
        },

        // ─── Résumé ──────────────────────────────────────────────────────

        format(value, decimals = 5) {
            return blank(value) ? '' : Number(value).toFixed(decimals).replace(/\.?0+$/, '')
        },

        get hasPosition() {
            return !blank(this.values.lat) && !blank(this.values.lng)
        },

        // ─── Ouvrir, valider, annuler ────────────────────────────────────

        // Ouvre le popup sur les valeurs enregistrées : ce qui n'a pas été validé la fois d'avant est oublié.
        openEditor() {
            this.resetDraft()
            this.$dispatch('open-modal', { id: config.modalId })
            this.$nextTick(() => this.showDraftOnMap())
        },

        resetDraft() {
            this.draft = {
                lat: blank(this.values.lat) ? null : Number(this.values.lat),
                lng: blank(this.values.lng) ? null : Number(this.values.lng),
                fields: Object.fromEntries(config.fields.map((field) => [field.key, blank(this.values.fields[field.key]) ? null : Number(this.values.fields[field.key])])),
            }
            this.error = ''
            this.query = ''
            this.results = []
            this.searched = false
            this.message = ''
        },

        cancel() {
            this.$dispatch('close-modal', { id: config.modalId })
        },

        async commit() {
            const hasLat = !blank(this.draft.lat)
            const hasLng = !blank(this.draft.lng)

            if (hasLat !== hasLng) {
                this.error = 'Renseignez la latitude et la longitude, ou aucune des deux.'

                return
            }

            if (!hasLat && config.required) {
                this.error = 'Choisissez une position : cliquez sur la carte, saisissez des coordonnées ou cherchez une adresse.'

                return
            }

            if ((hasLat && (Math.abs(this.draft.lat) > 90 || Math.abs(this.draft.lng) > 180))) {
                this.error = 'Latitude entre −90 et 90, longitude entre −180 et 180.'

                return
            }

            const zoomError = this.zoomError()

            if (zoomError) {
                this.error = zoomError

                return
            }

            this.error = ''
            this.saving = true

            try {
                const thumbnail = hasLat ? await this.captureThumbnail() : null

                this.values.lat = hasLat ? round(this.draft.lat, 5) : null
                this.values.lng = hasLng ? round(this.draft.lng, 5) : null

                for (const field of config.fields) {
                    const value = this.draft.fields[field.key]

                    this.values.fields[field.key] = blank(value) ? null : round(value, field.kind === 'zoom' ? 2 : 6)
                }

                if (config.hasThumbnail) {
                    this.values.thumbnail = thumbnail
                }

                this.$dispatch('close-modal', { id: config.modalId })
            } finally {
                this.saving = false
            }
        },

        // Le zoom minimum ne dépasse pas le zoom de départ, qui ne dépasse pas le zoom maximum.
        zoomError() {
            const of = (role) => {
                const key = config.fields.find((field) => field.role === role)?.key
                const value = key ? this.draft.fields[key] : null

                return blank(value) ? null : Number(value)
            }
            const [start, min, max] = [of('zoom'), of('min'), of('max')]

            if (min !== null && max !== null && min > max) {
                return 'Le zoom minimum doit être inférieur au zoom maximum.'
            }

            if (start !== null && min !== null && start < min) {
                return 'Le zoom de départ ne peut pas être inférieur au zoom minimum.'
            }

            if (start !== null && max !== null && start > max) {
                return 'Le zoom de départ ne peut pas dépasser le zoom maximum.'
            }

            return ''
        },

        clearPosition() {
            this.draft.lat = null
            this.draft.lng = null
            this.clearMarker()
        },

        // ─── La carte ────────────────────────────────────────────────────

        // La carte se crée quand ses scripts arrivent et que le popup existe : on attend les deux.
        waitForMap() {
            const attempt = () => {
                const element = this.mapElement()

                if (!element || !window.maplibregl || this.map || (config.mapPayload && !window.FilamentMap)) {
                    return false
                }

                this.bootMap(element)

                return true
            }

            if (attempt()) {
                return
            }

            const interval = window.setInterval(() => {
                if (attempt() || this.map) {
                    window.clearInterval(interval)
                }
            }, 50)
        },

        startView() {
            const payloadCenter = config.mapPayload?.map?.center ?? {}
            const zoomField = config.fields.find((field) => field.follow)?.key
            const savedZoom = zoomField ? this.values.fields[zoomField] : null

            return {
                lat: blank(this.values.lat) ? Number(payloadCenter.lat ?? config.defaults.lat) : Number(this.values.lat),
                lng: blank(this.values.lng) ? Number(payloadCenter.lng ?? config.defaults.lng) : Number(this.values.lng),
                zoom: !blank(savedZoom) ? Number(savedZoom) : Number(config.mapPayload?.map?.zoom ?? config.defaults.zoom),
            }
        },

        bootMap(element) {
            const view = this.startView()

            if (config.mapPayload) {
                window.FilamentMap.init(config.id, {
                    ...config.mapPayload,
                    map: { ...config.mapPayload.map, center: { lat: view.lat, lng: view.lng }, zoom: view.zoom },
                    state: { ...(config.mapPayload.state ?? {}), eventScope: config.scope, fitBounds: false, interactive: true },
                })
                this.map = window.FilamentMap.instances.get(config.id)?.map ?? null
            } else {
                this.map = new maplibregl.Map({ container: element, style: FALLBACK_STYLE(config.tiles), center: [view.lng, view.lat], zoom: view.zoom })
                this.map.addControl(new maplibregl.NavigationControl(), 'top-left')
            }

            if (!this.map) {
                return
            }

            if (config.mapPayload) {
                this.coordinatesPickedHandler = (event) => {
                    if (event.detail?.scope === config.scope) {
                        this.place(event.detail.lat, event.detail.lng)
                    }
                }
                window.addEventListener('filament-map:coordinates-picked', this.coordinatesPickedHandler)
            } else {
                this.map.on('click', (event) => this.place(event.lngLat.lat, event.lngLat.lng))
            }

            // Le zoom suit la carte quand on la zoome (pour le champ qui le demande) ; déplacer la carte ne change rien.
            this.map.on('zoomend', () => this.followZoom())
            this.map.once('idle', () => { this.settled = true })

            this.resizeObserver = new ResizeObserver((entries) => {
                const size = entries[0]?.contentRect

                if (size && size.width > 0 && size.height > 0) {
                    this.map.resize()
                }
            })
            this.resizeObserver.observe(element)
        },

        // Le popup vient de s'ouvrir : la carte se cale sur le brouillon (la position et le zoom enregistrés).
        showDraftOnMap() {
            if (!this.map) {
                return
            }

            const view = this.startView()
            const zoomField = config.fields.find((field) => field.follow)?.key
            const zoom = zoomField && !blank(this.draft.fields[zoomField]) ? this.draft.fields[zoomField] : view.zoom

            this.map.resize()
            this.settleAfter(() => this.map.jumpTo({ center: [view.lng, view.lat], zoom }))

            blank(this.draft.lat) ? this.clearMarker() : this.setMarker(this.draft.lat, this.draft.lng)
        },

        // Un déplacement qu'on commande nous-mêmes ne compte pas comme un zoom de l'utilisateur : la carte n'est « posée » qu'après.
        // « idle » ne vient pas quand la vue ne change pas : un délai court prend le relais.
        settleAfter(move) {
            this.settled = false
            move()

            const settle = () => { this.settled = true }

            this.map.once('idle', settle)
            window.setTimeout(settle, 700)
        },

        setMarker(lat, lng) {
            if (!this.marker) {
                this.marker = new maplibregl.Marker({ draggable: true })
                this.marker.on('dragend', () => {
                    const position = this.marker.getLngLat()

                    this.draft.lat = round(position.lat, 5)
                    this.draft.lng = round(this.wrapLongitude(position.lng), 5)
                })
            }

            this.marker.setLngLat([lng, lat]).addTo(this.map)
        },

        clearMarker() {
            this.marker?.remove()
        },

        wrapLongitude(lng) {
            return ((Number(lng) + 180) % 360 + 360) % 360 - 180
        },

        // Un clic sur la carte (ou un lieu trouvé) : le repère s'y pose. La carte, elle, ne bouge pas.
        place(lat, lng) {
            this.draft.lat = round(Math.max(-90, Math.min(90, Number(lat))), 5)
            this.draft.lng = round(this.wrapLongitude(lng), 5)
            this.setMarker(this.draft.lat, this.draft.lng)
        },

        // Des coordonnées saisies à la main : le repère s'y pose et la carte s'y rend.
        onCoordinatesTyped() {
            const lat = Number(this.draft.lat)
            const lng = Number(this.draft.lng)

            if (blank(this.draft.lat) || blank(this.draft.lng) || !Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
                return
            }

            this.setMarker(lat, lng)
            this.map?.easeTo({ center: [lng, lat], duration: 300 })
        },

        centerOnMarker() {
            if (this.marker && this.map) {
                this.map.easeTo({ center: this.marker.getLngLat(), duration: 400 })
            }
        },

        // ─── Zoom ────────────────────────────────────────────────────────

        followZoom() {
            if (!this.settled || !this.map) {
                return
            }

            for (const field of config.fields.filter((candidate) => candidate.follow)) {
                this.draft.fields[field.key] = round(this.map.getZoom(), 2)
            }
        },

        // Le bouton d'un champ de zoom : y reporte le zoom que la carte affiche.
        captureZoom(key) {
            if (this.map) {
                this.draft.fields[key] = round(this.map.getZoom(), 2)
            }
        },

        // ─── Recherche d'adresse ─────────────────────────────────────────

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

        // Va au lieu choisi : la carte s'y cale (sur son cadre quand on le connaît) et le repère s'y pose.
        pick(result) {
            const bounds = result.bounds

            if (bounds) {
                this.map.fitBounds([[bounds.west, bounds.south], [bounds.east, bounds.north]], { padding: 40, maxZoom: 17, duration: 600 })
            } else {
                this.map.flyTo({ center: [result.lng, result.lat], zoom: 14, duration: 600 })
            }

            this.place(result.lat, result.lng)
            this.results = []
            this.searched = false
            this.message = ''
        },

        // ─── Plein écran ─────────────────────────────────────────────────

        toggleFullscreen() {
            if (document.fullscreenElement) {
                document.exitFullscreen()

                return
            }

            const target = this.mapElement()?.closest('.fi-modal-window')

            target?.requestFullscreen?.().catch(() => {})
        },

        // ─── Miniature ───────────────────────────────────────────────────

        // Une image de la carte, centrée sur le repère, lue directement dans le canevas de la carte (aucun service ni
        // navigateur sans écran côté serveur), au format demandé (`config.thumbnail` : carrée avec repère par défaut). Elle
        // sert d'aperçu sous le bouton de modification.
        async captureThumbnail() {
            if (!config.hasThumbnail || !this.map || !this.marker) {
                return null
            }

            const map = this.map
            const zoomField = config.fields.find((field) => field.follow)?.key
            const zoom = zoomField && !blank(this.draft.fields[zoomField]) ? Number(this.draft.fields[zoomField]) : map.getZoom()
            const position = [this.draft.lng, this.draft.lat]

            try {
                this.settled = false
                map.jumpTo({ center: position, zoom })

                // Les fonds de carte arrivent par le réseau : on attend qu'ils soient dessinés (au plus quelques secondes).
                await new Promise((resolve) => {
                    const timer = window.setTimeout(resolve, 4000)

                    map.once('idle', () => { window.clearTimeout(timer); resolve() })
                    map.triggerRepaint()
                })

                // Le canevas WebGL n'est lisible que dans le rendu qui vient de le dessiner : on le lit dans « render ».
                return await new Promise((resolve) => {
                    map.once('render', () => {
                        try {
                            resolve(this.frameFrom(map.getCanvas()))
                        } catch (error) {
                            resolve(null)
                        }
                    })
                    map.triggerRepaint()
                })
            } catch (error) {
                return null
            } finally {
                window.setTimeout(() => { this.settled = true }, 700)
            }
        },

        // Le plus grand cadre au format de la miniature, pris au centre du canevas, puis réduit à sa taille.
        frameFrom(canvas) {
            const { width, height, marker } = config.thumbnail ?? { width: 192, height: 192, marker: true }
            const ratio = width / height
            const sourceWidth = Math.min(canvas.width, canvas.height * ratio)
            const sourceHeight = sourceWidth / ratio
            const output = document.createElement('canvas')

            output.width = width
            output.height = height

            const context = output.getContext('2d')

            context.drawImage(
                canvas,
                (canvas.width - sourceWidth) / 2, (canvas.height - sourceHeight) / 2, sourceWidth, sourceHeight,
                0, 0, width, height,
            )

            if (marker) {
                this.drawMarker(context, width / 2, height / 2)
            }

            const data = output.toDataURL('image/jpeg', 0.72)

            // Un canevas vide (fond pas encore chargé) donne une image minuscule : on n'en garde pas.
            return data.length > 1500 ? data : null
        },

        // Le repère, pointe en (x, y) : celui de la page est un élément HTML, absent du canevas.
        drawMarker(context, x, y) {
            context.fillStyle = '#e11d48'
            context.strokeStyle = '#ffffff'
            context.lineWidth = 3
            context.beginPath()
            context.arc(x, y - 14, 10, Math.PI * 0.8, Math.PI * 0.2, false)
            context.lineTo(x, y)
            context.closePath()
            context.fill()
            context.stroke()
            context.fillStyle = '#ffffff'
            context.beginPath()
            context.arc(x, y - 14, 4, 0, Math.PI * 2)
            context.fill()
        },
    }
}
