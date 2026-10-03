/**
 * Deux natures de fond cohabitent :
 * - un style MapLibre complet (couche de type "style" : MapTiler, TileCat…) :
 *   son URL décrit un fond entier (sources+layers+sprite+glyphs), il remplace
 *   donc le style actif de la carte au lieu de s'y ajouter (comportement
 *   "radio", cohérent avec le groupe `baseLayers` du sélecteur de couches).
 * - un simple flux raster {z}/{x}/{y} (type "tile") : ajouté comme
 *   source+layer `raster` dans le style courant, il peut cohabiter avec
 *   d'autres couches.
 * Une ancienne couche "tile" qui porte `options.style_url` reste un style.
 */
export function addStyleLayer(map, layer, context = {}) {
    const url = layer.source?.url ?? layer.options?.style_url ?? layer.options?.styleUrl

    return url ? createStyleLayer(layer, url, context) : null
}

export function addTileLayer(map, layer, context = {}) {
    const url = layer.source?.url

    if (!url) {
        return null
    }

    const styleUrl = layer.options?.style_url ?? layer.options?.styleUrl

    return styleUrl
        ? createStyleLayer(layer, styleUrl, context)
        : createRasterLayer(map, layer, url)
}

function createStyleLayer(layer, styleUrl, context) {
    const listeners = {}
    const api = {
        filamentMapKind: 'style',
        on(event, callback) {
            (listeners[event] ??= []).push(callback)
            return api
        },
        addTo() {
            context.activateStyle?.(styleUrl, () => emit('filament-map:ready'))
            return api
        },
        removeFrom() {
            // Les styles sont mutuellement exclusifs : on ne "cache" pas un
            // style, un autre prend simplement sa place.
            return api
        },
        destroy() {},
        getBounds: () => null,
        get filamentMapVisible() {
            return Boolean(context.isActiveStyle?.(styleUrl))
        },
    }

    function emit(event, detail) {
        for (const callback of listeners[event] ?? []) {
            callback(detail)
        }
    }

    // « Visible par défaut » choisit le fond de départ : une fois un fond actif
    // (celui de départ, ou un autre choisi depuis), refaire les couches après
    // un changement de style ne doit pas le reprendre.
    if (layer.visible && !context.hasActiveStyle?.()) {
        api.addTo()
    }

    return api
}

function createRasterLayer(map, layer, url) {
    const id = `filament-map-raster-${layer.key}`
    const listeners = {}

    const ensureRegistered = () => {
        if (!map.getSource(id)) {
            map.addSource(id, {
                type: 'raster',
                tiles: [url],
                tileSize: 256,
                attribution: layer.options?.attribution,
            })
        }

        if (!map.getLayer(id)) {
            map.addLayer({ id, type: 'raster', source: id })
        }
    }

    const api = {
        filamentMapKind: 'raster',
        on(event, callback) {
            (listeners[event] ??= []).push(callback)
            return api
        },
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
        getBounds: () => null,
        get filamentMapVisible() {
            return map.getLayer(id) ? map.getLayoutProperty(id, 'visibility') !== 'none' : false
        },
    }

    if (layer.visible) {
        api.addTo()
    }

    return api
}
