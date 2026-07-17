export function addMarkerLayer(map, points) {
    const group = L.layerGroup()

    for (const point of points) {
        if (point.visible === false || !point.position) {
            continue
        }

        const marker = L.marker([point.position.lat, point.position.lng], {
            title: point.name,
            ...(point.options?.marker ?? {}),
        })

        if (point.tooltip) {
            marker.bindTooltip(point.tooltip)
        }

        if (point.popup) {
            marker.bindPopup(point.popup)
        }

        marker.on('click', () => {
            window.dispatchEvent(new CustomEvent('filament-map:point-clicked', {
                detail: { point },
            }))
        })

        marker.addTo(group)
    }

    group.addTo(map)

    return group
}
