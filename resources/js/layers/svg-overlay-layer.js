export function addSvgOverlayLayer(map, layer) {
    if (!layer.source?.url || !layer.options?.bounds) {
        return null
    }

    const bounds = [
        [layer.options.bounds.southWest.lat, layer.options.bounds.southWest.lng],
        [layer.options.bounds.northEast.lat, layer.options.bounds.northEast.lng],
    ]

    const overlay = L.imageOverlay(layer.source.url, bounds, layer.options ?? {})

    if (layer.visible) {
        overlay.addTo(map)
    }

    return overlay
}
