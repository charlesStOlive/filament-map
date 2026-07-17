export function addTileLayer(map, layer) {
    const url = layer.source?.url

    if (!url) {
        return null
    }

    const tileLayer = L.tileLayer(url, layer.options ?? {})

    if (layer.visible) {
        tileLayer.addTo(map)
    }

    return tileLayer
}
