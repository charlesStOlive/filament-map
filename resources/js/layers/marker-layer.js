export function addMarkerLayer(map, points, context = {}) {
    const group = L.featureGroup()
    const markers = new Map()
    let markerCount = 0

    for (const point of points) {
        if (point.visible === false || !point.position) {
            continue
        }

        const marker = L.marker([point.position.lat, point.position.lng], {
            title: point.name,
            ...(point.options?.marker ?? {}),
            zIndexOffset: String(point.id) === String(context.selectedPointId) ? 1000 : (point.options?.marker?.zIndexOffset ?? 0),
        })

        if (point.tooltip) {
            marker.bindTooltip(point.tooltip)
        }

        if (point.popup) {
            marker.bindPopup(point.popup)
        }

        marker.on('click', () => {
            context.onPointClick?.(point)
        })

        marker.addTo(group)
        markers.set(String(point.id), marker)
        markerCount += 1
    }

    if (markerCount === 0) {
        return null
    }

    group.getFilamentMarker = (pointId) => markers.get(String(pointId))
    group.addTo(map)

    return group
}
