/**
 * Les formes d'un marqueur, à leur taille par défaut (en px) et avec le point qui se pose sur la position : la pointe
 * d'une épingle, le centre des autres.
 */
const SHAPES = {
    pin: { width: 30, height: 40, anchor: 'bottom' },
    circle: { width: 34, height: 34, anchor: 'center' },
    star: { width: 34, height: 34, anchor: 'center' },
    svg: { width: 34, height: 34, anchor: 'center' },
}

const PIN_SVG = '<svg viewBox="0 0 30 40" aria-hidden="true"><path d="M15 1.5C7.5 1.5 1.5 7.5 1.5 14.8 1.5 25 15 38.5 15 38.5S28.5 25 28.5 14.8C28.5 7.5 22.5 1.5 15 1.5Z" fill="currentColor" stroke="#fff" stroke-width="1.5"/></svg>'

const STAR_SVG = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1.8l3.1 6.6 7.2.9-5.3 5 1.4 7.2L12 18l-6.4 3.5L7 14.3l-5.3-5 7.2-.9L12 1.8Z" fill="currentColor" stroke="#fff" stroke-width="1.2" stroke-linejoin="round"/></svg>'

const STYLES = `
.filament-map-marker { position: relative; width: var(--filament-map-marker-width); height: var(--filament-map-marker-height); cursor: pointer;
    color: var(--filament-map-marker-color, #3fb1ce); filter: drop-shadow(0 1px 2px rgb(0 0 0 / 0.35)); }
.filament-map-marker__shape { position: absolute; inset: 0; }
.filament-map-marker__shape > svg { display: block; width: 100%; height: 100%; }
.filament-map-marker--circle .filament-map-marker__shape { box-sizing: border-box; border: 2px solid #fff; border-radius: 50%; background: currentColor; }
.filament-map-marker__content { position: absolute; top: 50%; left: 50%; display: flex; align-items: center; justify-content: center;
    width: 60%; aspect-ratio: 1; overflow: hidden; transform: translate(-50%, -50%);
    color: var(--filament-map-marker-content-color, #fff); font: 600 11px/1 system-ui, sans-serif; white-space: nowrap; }
.filament-map-marker--pin .filament-map-marker__content { top: 37%; width: 66%; }
.filament-map-marker--star .filament-map-marker__content { top: 55%; width: 38%; }
.filament-map-marker__content > svg { width: 75%; height: 75%; }
.filament-map-marker__content.is-image { border: 1.5px solid #fff; border-radius: 50%; background: #fff; }
.filament-map-marker--circle .filament-map-marker__content.is-image { width: calc(100% - 4px); border: 0; }
.filament-map-marker__content.is-image > img { display: block; width: 100%; height: 100%; object-fit: cover; }
`

function ensureStyles() {
    if (document.querySelector('style[data-filament-map-markers]')) {
        return
    }

    const style = document.createElement('style')
    style.dataset.filamentMapMarkers = ''
    style.textContent = STYLES
    document.head.appendChild(style)
}

/**
 * Le marqueur tel que son type le décrit (`point.appearance`, préparée par MapPayloadBuilder) : sa forme, ce qu'elle
 * contient (icône, image, texte ou rien), sa couleur, sa taille et ses variables de style. L'élément que MapLibre
 * place n'est qu'un cadre : le dessin est dans son enfant, qu'une page peut donc agrandir ou décaler sans gêner
 * MapLibre, qui positionne le cadre avec `transform`.
 */
function markerElement(point, markerOptions) {
    const appearance = point.appearance
    const shape = SHAPES[appearance.shape] ? appearance.shape : 'pin'
    const scale = Number(markerOptions.scale) > 0 ? Number(markerOptions.scale) : 1
    const baseWidth = Number(appearance.size?.width) || SHAPES[shape].width
    // Une largeur sans hauteur garde les proportions de la forme.
    const baseHeight = Number(appearance.size?.height) || baseWidth * SHAPES[shape].height / SHAPES[shape].width
    const width = baseWidth * scale
    const height = baseHeight * scale

    const element = document.createElement('div')
    const body = document.createElement('div')
    body.className = `filament-map-marker filament-map-marker--${shape}`
    body.style.setProperty('--filament-map-marker-width', `${width}px`)
    body.style.setProperty('--filament-map-marker-height', `${height}px`)

    const color = appearance.color ?? markerOptions.color

    if (color) {
        body.style.setProperty('--filament-map-marker-color', color)
    }

    // Les variables de style du type ou du point : `--nom` ou une propriété CSS, posées sur le dessin.
    for (const [property, value] of Object.entries(appearance.css ?? {})) {
        if (value !== null && value !== '') {
            body.style.setProperty(property, String(value))
        }
    }

    const shapeElement = document.createElement('div')
    shapeElement.className = 'filament-map-marker__shape'
    shapeElement.innerHTML = shape === 'pin' ? PIN_SVG : shape === 'star' ? STAR_SVG : shape === 'svg' ? appearance.svg : ''
    body.appendChild(shapeElement)

    const content = appearance.content ?? {}

    if (content.type !== 'none' && content.value) {
        const slot = document.createElement('div')
        slot.className = 'filament-map-marker__content'

        if (content.type === 'image') {
            const image = document.createElement('img')
            image.src = content.value
            image.alt = ''
            image.decoding = 'async'
            slot.classList.add('is-image')
            slot.appendChild(image)
        } else if (content.type === 'icon') {
            slot.innerHTML = content.html ?? ''
        } else {
            slot.textContent = String(content.value)
        }

        body.appendChild(slot)
    }

    element.setAttribute('aria-label', point.name ?? '')
    element.appendChild(body)

    return { element, anchor: SHAPES[shape].anchor }
}

/**
 * MapLibre n'a pas de "groupe de marqueurs" : chaque `maplibregl.Marker` vit
 * seule sur la carte. On les garde dans une Map locale pour reproduire
 * l'API attendue par MapLibreMapInstance (getFilamentMarker, destroy...).
 *
 * `point.options.marker` passe aux options de MapLibre (`className`, `anchor`, `offset`…) ; `scale` et `color` y
 * agrandissent et colorent le dessin. Un point sans `appearance` garde le marqueur par défaut de MapLibre.
 */
export function addMarkerLayer(map, points, context = {}) {
    const markers = new Map()
    let visible = true

    ensureStyles()

    for (const point of points) {
        if (point.visible === false || !point.position) {
            continue
        }

        const markerOptions = point.options?.marker ?? {}
        const drawn = point.appearance ? markerElement(point, markerOptions) : null

        const marker = new maplibregl.Marker({
            draggable: false,
            ...(drawn ? { element: drawn.element, anchor: drawn.anchor } : {}),
            ...markerOptions,
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
