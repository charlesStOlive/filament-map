/**
 * MapLibre n'a pas de "groupe de marqueurs" : chaque `maplibregl.Marker` vit
 * seule sur la carte. On les garde dans une Map locale pour reproduire
 * l'API attendue par MapLibreMapInstance (getFilamentMarker, destroy...).
 */
export function addMarkerLayer(map, points, context = {}) {
    const markers = new Map()
    let visible = true

    for (const point of points) {
        if (point.visible === false || !point.position) {
            continue
        }

        const marker = new maplibregl.Marker({
            draggable: false,
            ...(point.options?.marker ?? {}),
        }).setLngLat([point.position.lng, point.position.lat])

        if (String(point.id) === String(context.selectedPointId)) {
            marker.getElement().classList.add('filament-map-marker-selected')
        }

        if (point.popup) {
            marker.setPopup(new maplibregl.Popup({ offset: 24 }).setHTML(point.popup))
        } else if (point.tooltip) {
            const tooltip = new maplibregl.Popup({ offset: 24, closeButton: false, closeOnClick: false })
                .setText(point.tooltip)
            marker.getElement().addEventListener('mouseenter', () => tooltip.setLngLat(marker.getLngLat()).addTo(map))
            marker.getElement().addEventListener('mouseleave', () => tooltip.remove())
        }

        marker.getElement().addEventListener('click', () => context.onPointClick?.(point))
        marker.getElement().addEventListener('mouseenter', () => context.onPointHover?.(point, true))
        marker.getElement().addEventListener('mouseleave', () => context.onPointHover?.(point, false))
        markers.set(String(point.id), marker)
    }

    if (markers.size === 0) {
        return null
    }

    const api = {
        filamentMapKind: 'points',
        on: () => api,
        addTo() {
            visible = true
            for (const marker of markers.values()) {
                marker.addTo(map)
            }
            return api
        },
        removeFrom() {
            visible = false
            for (const marker of markers.values()) {
                marker.remove()
            }
            return api
        },
        destroy() {
            for (const marker of markers.values()) {
                marker.remove()
            }
            markers.clear()
        },
        getBounds() {
            const positions = [...markers.values()].map((marker) => marker.getLngLat())

            if (!positions.length) {
                return null
            }

            const lngs = positions.map((p) => p.lng)
            const lats = positions.map((p) => p.lat)

            return [[Math.min(...lngs), Math.min(...lats)], [Math.max(...lngs), Math.max(...lats)]]
        },
        get filamentMapVisible() {
            return visible
        },
        getFilamentMarker(pointId) {
            return markers.get(String(pointId))
        },
    }

    api.addTo()

    return api
}
