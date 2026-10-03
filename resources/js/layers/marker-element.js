/**
 * Le dessin d'un marqueur, d'après son `appearance` préparée par MapPayloadBuilder::appearance() : la forme (un SVG,
 * fourni ou personnalisé), sa zone de contenu (`slot`, en % de la forme) où se pose l'image, l'icône ou le texte, sa
 * couleur, sa taille et ses variables de style. La carte (marker-layer.js) et l'aperçu d'un type
 * (forms/components/marker-preview) dessinent avec ce même code.
 */

/**
 * Une apparence d'avant les formes en SVG (la version figée d'un parcours publié plus tôt) n'a ni `svg` ni `slot` :
 * elle est dessinée en épingle.
 */
const LEGACY = {
    svg: '<svg viewBox="0 0 30 40"><path d="M15 1.5C7.5 1.5 1.5 7.5 1.5 14.8 1.5 25 15 38.5 15 38.5S28.5 25 28.5 14.8C28.5 7.5 22.5 1.5 15 1.5Z" fill="currentColor" stroke="#fff" stroke-width="1.5"/></svg>',
    slot: { left: 16.667, top: 12, width: 66.667, height: 50, radius: '50%' },
    anchor: 'bottom',
    width: 30,
    height: 40,
}

const STYLES = `
.filament-map-marker { position: relative; width: var(--filament-map-marker-width); height: var(--filament-map-marker-height); cursor: pointer;
    color: var(--filament-map-marker-color, #3fb1ce); filter: drop-shadow(0 1px 2px rgb(0 0 0 / 0.35)); }
.filament-map-marker__shape { position: absolute; inset: 0; }
.filament-map-marker__shape > svg { display: block; width: 100%; height: 100%; overflow: visible; }
.filament-map-marker__content { position: absolute; display: flex; align-items: center; justify-content: center; overflow: hidden;
    color: var(--filament-map-marker-content-color, #fff); font: 600 calc(var(--filament-map-marker-slot-size) * 0.55)/1 system-ui, sans-serif;
    white-space: nowrap; }
.filament-map-marker__content > svg { width: 75%; height: 75%; }
.filament-map-marker__content.is-image { background: #fff; }
.filament-map-marker__content.is-image > img { display: block; width: 100%; height: 100%; object-fit: cover; }
.filament-map-marker__content.is-outlined { outline: 1px dashed rgb(0 0 0 / 0.55); box-shadow: 0 0 0 1px rgb(255 255 255 / 0.7); }
`

export function ensureMarkerStyles() {
    if (document.querySelector('style[data-filament-map-markers]')) {
        return
    }

    const style = document.createElement('style')
    style.dataset.filamentMapMarkers = ''
    style.textContent = STYLES
    document.head.appendChild(style)
}

/**
 * @param {object} appearance  `point.appearance`
 * @param {object} options     `scale` (agrandit le dessin), `color` (si l'apparence n'en a pas), `label` (nom
 *                             accessible), `outlineSlot` (l'aperçu : la zone de contenu en pointillés, même vide)
 * @returns {{ element: HTMLElement, anchor: string, offset: [number, number], rotation: number }} L'élément à donner à
 *   MapLibre — un cadre : le dessin est dans son enfant, qu'une page peut agrandir ou décaler sans gêner MapLibre, qui
 *   positionne le cadre avec `transform` —, et ses options de position : l'ancrage, le décalage en px (le % de
 *   l'apparence appliqué à la taille dessinée) et la rotation en degrés, autour de l'ancrage.
 */
export function markerElement(appearance, { scale = 1, color = null, label = '', outlineSlot = false } = {}) {
    ensureMarkerStyles()

    const legacy = !appearance.svg
    const factor = Number(scale) > 0 ? Number(scale) : 1
    // La taille est résolue par le serveur ; seule une apparence d'avant les formes en SVG peut n'en avoir qu'une partie.
    const baseWidth = Number(appearance.size?.width) || LEGACY.width
    const baseHeight = Number(appearance.size?.height) || baseWidth * LEGACY.height / LEGACY.width
    const width = baseWidth * factor
    const height = baseHeight * factor
    const slot = legacy ? LEGACY.slot : appearance.slot

    const element = document.createElement('div')
    const body = document.createElement('div')
    body.className = `filament-map-marker filament-map-marker--${appearance.shape ?? 'pin'}`
    body.style.setProperty('--filament-map-marker-width', `${width}px`)
    body.style.setProperty('--filament-map-marker-height', `${height}px`)

    if (appearance.color ?? color) {
        body.style.setProperty('--filament-map-marker-color', appearance.color ?? color)
    }

    // Les variables de style du type ou du point : `--nom` ou une propriété CSS, posées sur le dessin.
    for (const [property, value] of Object.entries(appearance.css ?? {})) {
        if (value !== null && value !== '') {
            body.style.setProperty(property, String(value))
        }
    }

    const shape = document.createElement('div')
    shape.className = 'filament-map-marker__shape'
    // Le SVG vient du serveur, nettoyé (MarkerSvg) : jamais d'une saisie du navigateur.
    shape.innerHTML = legacy ? LEGACY.svg : appearance.svg
    body.appendChild(shape)

    const content = appearance.content ?? {}
    const filled = content.type && content.type !== 'none' && content.value

    if (slot && (filled || outlineSlot)) {
        const zone = document.createElement('div')
        zone.className = 'filament-map-marker__content'
        Object.assign(zone.style, {
            left: `${slot.left}%`,
            top: `${slot.top}%`,
            width: `${slot.width}%`,
            height: `${slot.height}%`,
            borderRadius: slot.radius,
        })
        zone.style.setProperty('--filament-map-marker-slot-size', `${height * slot.height / 100}px`)
        zone.classList.toggle('is-outlined', outlineSlot)

        if (filled && content.type === 'image') {
            const image = document.createElement('img')
            image.src = content.value
            image.alt = ''
            image.decoding = 'async'
            zone.classList.add('is-image')
            zone.appendChild(image)
        } else if (filled && content.type === 'icon') {
            zone.innerHTML = content.html ?? ''
        } else if (filled) {
            zone.textContent = String(content.value)
        }

        body.appendChild(zone)
    }

    element.setAttribute('aria-label', label ?? '')
    element.appendChild(body)

    return {
        element,
        anchor: legacy ? LEGACY.anchor : (appearance.anchor ?? 'center'),
        offset: [(Number(appearance.offset?.x) || 0) * width / 100, (Number(appearance.offset?.y) || 0) * height / 100],
        rotation: Number(appearance.rotation) || 0,
    }
}
