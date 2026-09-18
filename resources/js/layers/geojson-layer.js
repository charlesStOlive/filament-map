/**
 * MapLibre n'a pas d'équivalent direct à `L.geoJSON` : on enregistre une
 * source `geojson` (qui sait elle-même aller chercher une URL) puis jusqu'à
 * trois layers filtrés par type de géométrie (remplissage, ligne, point),
 * stylés via des expressions `case` qui rejouent les styleRules.
 */
export function addGeoJsonLayer(map, layer, context = {}) {
    const source = layer.source ?? {}
    const data = source.type === 'json' ? source.data : source.url

    if (!data) {
        return null
    }

    const sourceId = `filament-map-geojson-${layer.key}`
    const layerIds = {
        fill: `${sourceId}-fill`,
        line: `${sourceId}-line`,
        circle: `${sourceId}-circle`,
    }
    const listeners = {}
    const clickHandler = (event) => {
        const feature = event.features?.[0]

        if (!feature) {
            return
        }

        window.dispatchEvent(new CustomEvent('filament-map:feature-clicked', {
            detail: { mapId: context.mapId, scope: context.scope, layer, feature },
        }))
    }
    const pointerEnter = () => { map.getCanvas().style.cursor = 'pointer' }
    const pointerLeave = () => { map.getCanvas().style.cursor = '' }

    const emit = (event, detail) => {
        for (const callback of listeners[event] ?? []) {
            callback(detail)
        }
    }

    const registerInteractions = () => {
        for (const id of Object.values(layerIds)) {
            map.on('click', id, clickHandler)
            map.on('mouseenter', id, pointerEnter)
            map.on('mouseleave', id, pointerLeave)
        }
    }

    const ensureRegistered = () => {
        if (map.getSource(sourceId)) {
            return
        }

        map.addSource(sourceId, { type: 'geojson', data })
        map.addLayer({
            id: layerIds.fill,
            type: 'fill',
            source: sourceId,
            filter: ['==', ['geometry-type'], 'Polygon'],
            paint: {
                'fill-color': styleExpression(layer, 'fillColor', '#3388ff'),
                'fill-opacity': styleExpression(layer, 'fillOpacity', 0.2),
            },
        })
        map.addLayer({
            id: layerIds.line,
            type: 'line',
            source: sourceId,
            filter: ['in', ['geometry-type'], ['literal', ['LineString', 'Polygon']]],
            paint: {
                'line-color': styleExpression(layer, 'color', '#3388ff'),
                'line-width': styleExpression(layer, 'weight', 3),
                ...(layer.style?.dashArray ? { 'line-dasharray': parseDashArray(layer.style.dashArray) } : {}),
            },
        })
        map.addLayer({
            id: layerIds.circle,
            type: 'circle',
            source: sourceId,
            filter: ['==', ['geometry-type'], 'Point'],
            paint: {
                'circle-color': styleExpression(layer, 'fillColor', styleExpression(layer, 'color', '#3388ff')),
                'circle-radius': styleExpression(layer, 'radius', 6),
                'circle-opacity': styleExpression(layer, 'fillOpacity', 1),
            },
        })
        registerInteractions()

        const registeredSource = map.getSource(sourceId)

        if (typeof registeredSource?.once === 'function') {
            registeredSource.once('data', () => emit('filament-map:ready'))
        }
    }

    const api = {
        filamentMapKind: 'geojson',
        on(event, callback) {
            (listeners[event] ??= []).push(callback)
            return api
        },
        addTo() {
            ensureRegistered()

            for (const id of Object.values(layerIds)) {
                map.setLayoutProperty(id, 'visibility', 'visible')
            }

            return api
        },
        removeFrom() {
            for (const id of Object.values(layerIds)) {
                if (map.getLayer(id)) {
                    map.setLayoutProperty(id, 'visibility', 'none')
                }
            }

            return api
        },
        destroy() {
            for (const id of Object.values(layerIds)) {
                if (map.getLayer(id)) {
                    map.off('click', id, clickHandler)
                    map.off('mouseenter', id, pointerEnter)
                    map.off('mouseleave', id, pointerLeave)
                    map.removeLayer(id)
                }
            }

            if (map.getSource(sourceId)) {
                map.removeSource(sourceId)
            }
        },
        getBounds() {
            if (!map.getSource(sourceId)) {
                return null
            }

            const features = map.querySourceFeatures(sourceId)

            return features.length ? boundsOf(features) : null
        },
        /** Surligne les features dont `property` vaut `value` via une expression `case`. */
        highlightFeature(property, value, style = {}) {
            const match = ['==', ['get', property], value]

            if (map.getLayer(layerIds.fill)) {
                map.setPaintProperty(layerIds.fill, 'fill-color', ['case', match, style.fillColor ?? style.color ?? '#f59e0b', styleExpression(layer, 'fillColor', '#3388ff')])
            }

            if (map.getLayer(layerIds.line)) {
                map.setPaintProperty(layerIds.line, 'line-color', ['case', match, style.color ?? '#f59e0b', styleExpression(layer, 'color', '#3388ff')])
                map.setPaintProperty(layerIds.line, 'line-width', ['case', match, style.weight ?? 5, styleExpression(layer, 'weight', 3)])
            }

            if (map.getLayer(layerIds.circle)) {
                map.setPaintProperty(layerIds.circle, 'circle-color', ['case', match, style.fillColor ?? style.color ?? '#f59e0b', styleExpression(layer, 'fillColor', styleExpression(layer, 'color', '#3388ff'))])
            }
        },
        get filamentMapVisible() {
            return map.getLayer(layerIds.fill) ? map.getLayoutProperty(layerIds.fill, 'visibility') !== 'none' : false
        },
    }

    if (layer.visible) {
        api.addTo()
    }

    return api
}

function styleExpression(layer, property, fallback) {
    const base = layer.style?.[property] ?? fallback
    const rules = (layer.styleRules ?? []).filter((rule) => rule.style?.[property] !== undefined)

    if (!rules.length) {
        return base
    }

    const expression = ['case']

    for (const rule of rules) {
        expression.push(['==', ['get', rule.when?.property], rule.when?.equals], rule.style[property])
    }

    expression.push(base)

    return expression
}

function parseDashArray(value) {
    if (Array.isArray(value)) {
        return value.map(Number)
    }

    return String(value).split(/[\s,]+/).map(Number).filter((n) => Number.isFinite(n))
}

function boundsOf(features) {
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
}
