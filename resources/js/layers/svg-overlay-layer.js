/**
 * MapLibre géoréférence une image via une source `image` définie par ses 4
 * coins (sens horaire depuis le coin haut-gauche), là où Leaflet se
 * contentait d'un simple rectangle sud-ouest/nord-est.
 */
export function addSvgOverlayLayer(map, layer) {
    if (!layer.source?.url || !layer.options?.bounds) {
        return null
    }

    const { southWest, northEast } = layer.options.bounds
    const coordinates = [
        [southWest.lng, northEast.lat],
        [northEast.lng, northEast.lat],
        [northEast.lng, southWest.lat],
        [southWest.lng, southWest.lat],
    ]
    const id = `filament-map-svg-${layer.key}`

    const ensureRegistered = () => {
        if (!map.getSource(id)) {
            map.addSource(id, { type: 'image', url: layer.source.url, coordinates })
        }

        if (!map.getLayer(id)) {
            map.addLayer({ id, type: 'raster', source: id })
        }
    }

    const api = {
        filamentMapKind: 'svg_overlay',
        on: () => api,
        addTo() {
            ensureRegistered()
            map.setLayoutProperty(id, 'visibility', 'visible')
            return api
        },
        removeFrom() {
            if (map.getLayer(id)) {
                map.setLayoutProperty(id, 'visibility', 'none')
            }
            return api
        },
        destroy() {
            if (map.getLayer(id)) {
                map.removeLayer(id)
            }
            if (map.getSource(id)) {
                map.removeSource(id)
            }
        },
        getBounds: () => [[southWest.lng, southWest.lat], [northEast.lng, northEast.lat]],
        get filamentMapVisible() {
            return map.getLayer(id) ? map.getLayoutProperty(id, 'visibility') !== 'none' : false
        },
    }

    if (layer.visible) {
        api.addTo()
    }

    return api
}
