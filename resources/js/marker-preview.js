import { markerElement } from './layers/marker-element.js'

/** Le point de la forme qu'un ancrage pose sur la position, en fraction de sa largeur et de sa hauteur. */
const ANCHORS = {
    center: [0.5, 0.5],
    top: [0.5, 0],
    bottom: [0.5, 1],
    left: [0, 0.5],
    right: [1, 0.5],
    'top-left': [0, 0],
    'top-right': [1, 0],
    'bottom-left': [0, 1],
    'bottom-right': [1, 1],
}

/** Le plus fort agrandissement d'une scène « fit », et la part de la scène que le dessin y occupe au plus. */
const MAX_ZOOM = 4
const FILL = 0.7

/**
 * Dessine un marqueur dans une scène de l'aperçu d'un type (partials/marker-preview) comme MapLibre le poserait :
 * son ancrage sur la position du point, puis son décalage et sa rotation autour de l'ancrage.
 *
 * Le dessin réel — forme décalée, pivotée, et la position elle-même — est centré dans la scène : une épingle, qui
 * pend au-dessus de sa position, ne sort pas du cadre. `fit` agrandit le dessin autant qu'il tient (au plus × 4).
 *
 * La scène contient `[data-marker-holder]` (où se pose le marqueur), et peut contenir `[data-marker-cross]` (la
 * position du point) et `[data-marker-zoom]` (l'agrandissement retenu).
 *
 * @returns {number} L'agrandissement retenu.
 */
export function drawMarkerStage(stage, appearance, { fit = false, outlineSlot = false } = {}) {
    const width = Number(appearance.size?.width) || 30
    const height = Number(appearance.size?.height) || 40
    const [ax, ay] = ANCHORS[appearance.anchor] ?? ANCHORS.center
    const angle = ((Number(appearance.rotation) || 0) * Math.PI) / 180
    const offsetX = ((Number(appearance.offset?.x) || 0) * width) / 100
    const offsetY = ((Number(appearance.offset?.y) || 0) * height) / 100

    // Les coins du dessin par rapport à la position, à l'échelle 1 : pivotés autour de l'ancrage, puis décalés. La
    // position elle-même est gardée dans le cadre.
    const points = [[0, 0], [1, 0], [0, 1], [1, 1]].map(([fx, fy]) => {
        const x = (fx - ax) * width
        const y = (fy - ay) * height

        return [x * Math.cos(angle) - y * Math.sin(angle) + offsetX, x * Math.sin(angle) + y * Math.cos(angle) + offsetY]
    })
    points.push([0, 0])

    const xs = points.map(([x]) => x)
    const ys = points.map(([, y]) => y)
    const box = { left: Math.min(...xs), right: Math.max(...xs), top: Math.min(...ys), bottom: Math.max(...ys) }

    const stageWidth = stage.clientWidth || 200
    const stageHeight = stage.clientHeight || 96
    const scale = fit
        ? Math.min(MAX_ZOOM, (FILL * stageWidth) / Math.max(box.right - box.left, 1), (FILL * stageHeight) / Math.max(box.bottom - box.top, 1))
        : 1

    // La position du point dans la scène, pour que le dessin y soit centré.
    const x = stageWidth / 2 - (scale * (box.left + box.right)) / 2
    const y = stageHeight / 2 - (scale * (box.top + box.bottom)) / 2

    const { element } = markerElement(appearance, { scale, outlineSlot })
    const holder = stage.querySelector('[data-marker-holder]')
    Object.assign(holder.style, {
        position: 'absolute',
        left: `${x}px`,
        top: `${y}px`,
        transformOrigin: `${ax * 100}% ${ay * 100}%`,
        transform: `translate(${-ax * 100}%, ${-ay * 100}%) translate(${offsetX * scale}px, ${offsetY * scale}px) rotate(${appearance.rotation || 0}deg)`,
    })
    holder.replaceChildren(element)

    const cross = stage.querySelector('[data-marker-cross]')

    if (cross) {
        Object.assign(cross.style, { left: `${x}px`, top: `${y}px` })
    }

    const zoom = stage.querySelector('[data-marker-zoom]')

    if (zoom) {
        zoom.textContent = `× ${scale.toLocaleString('fr-FR', { maximumFractionDigits: 1 })}`
    }

    return scale
}
