export function addGeoJsonLayer(map, layer) {
    const applyStyleRules = (feature) => {
        const baseStyle = layer.style ?? {}

        for (const rule of layer.styleRules ?? []) {
            const property = rule.when?.property
            const equals = rule.when?.equals

            if (property && feature?.properties?.[property] === equals) {
                return { ...baseStyle, ...(rule.style ?? {}) }
            }
        }

        return baseStyle
    }

    const createLayer = (data) => {
        const geoJsonLayer = L.geoJSON(data, {
            style: applyStyleRules,
            onEachFeature(feature, leafletLayer) {
                leafletLayer.on('click', () => {
                    window.dispatchEvent(new CustomEvent('filament-map:feature-clicked', {
                        detail: { layer, feature },
                    }))
                })
            },
            ...(layer.options ?? {}),
        })

        if (layer.visible) {
            geoJsonLayer.addTo(map)
        }

        return geoJsonLayer
    }

    if (layer.source?.type === 'json') {
        return createLayer(layer.source.data)
    }

    if (layer.source?.type === 'url' && layer.source.url) {
        const placeholderLayer = L.geoJSON(null)

        if (layer.visible) {
            placeholderLayer.addTo(map)
        }

        fetch(layer.source.url)
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`)
                }

                return response.json()
            })
            .then((data) => {
                if (placeholderLayer.filamentMapDisposed) {
                    return
                }

                placeholderLayer.addData(data)
                placeholderLayer.setStyle(applyStyleRules)

                placeholderLayer.fire('filament-map:ready')
            })
            .catch((error) => placeholderLayer.fire('filament-map:error', { error }))

        return placeholderLayer
    }

    return null
}
