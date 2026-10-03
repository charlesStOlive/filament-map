<?php

namespace CharlesStOlive\FilamentMap\Support;

/**
 * Les formes de marqueur : celles fournies (épingle, cercle, étoile), écrites comme un SVG personnalisé — avec leur
 * zone de contenu `data-slot` et leur ancrage `data-anchor` (voir MarkerSvg) —, et la forme `svg`, celle que le type
 * dessine lui-même. Une seule mécanique, donc, pour toutes.
 */
final class MarkerShapes
{
    /** @var array<string, string> */
    public const BUILT_IN = [
        'pin' => '<svg viewBox="0 0 30 40" data-anchor="bottom">'
            .'<path d="M15 1.5C7.5 1.5 1.5 7.5 1.5 14.8 1.5 25 15 38.5 15 38.5S28.5 25 28.5 14.8C28.5 7.5 22.5 1.5 15 1.5Z" fill="currentColor" stroke="#fff" stroke-width="1.5"/>'
            .'<circle data-slot="" cx="15" cy="14.8" r="10"/></svg>',
        'circle' => '<svg viewBox="0 0 34 34">'
            .'<circle cx="17" cy="17" r="16" fill="currentColor" stroke="#fff" stroke-width="2"/>'
            .'<circle data-slot="" cx="17" cy="17" r="14"/></svg>',
        'star' => '<svg viewBox="0 0 24 24">'
            .'<path d="M12 1.8l3.1 6.6 7.2.9-5.3 5 1.4 7.2L12 18l-6.4 3.5L7 14.3l-5.3-5 7.2-.9L12 1.8Z" fill="currentColor" stroke="#fff" stroke-width="1.2" stroke-linejoin="round"/>'
            .'<circle data-slot="" cx="12" cy="13" r="4.5"/></svg>',
    ];

    /** Les libellés, dans le formulaire d'un type. */
    public const LABELS = [
        'pin' => 'Épingle',
        'circle' => 'Cercle',
        'star' => 'Étoile',
        'svg' => 'SVG personnalisé',
    ];

    /** Le plus grand côté d'un marqueur dont la taille n'est pas réglée, en px : l'épingle mesure 30 × 40. */
    public const DEFAULT_SIZE = 40;

    /** Les côtés par défaut des formes fournies, quand leur proportion ne suffit pas (le cercle est plus petit). */
    private const DEFAULT_SIZES = ['pin' => [30, 40], 'circle' => [34, 34], 'star' => [34, 34]];

    /**
     * La forme d'un style de marqueur, lue (voir MarkerSvg::shape()), avec son nom et sa taille en px : celle du style,
     * sinon celle de la forme ; une largeur seule garde les proportions. Un SVG personnalisé illisible laisse place à
     * l'épingle.
     *
     * @param  array<string, mixed>  $style  Le `marker_style` d'un type ou d'un point.
     * @return array{name: string, svg: string, ratio: float, slot: array<string, float|string>|null, anchor: string, width: float, height: float}
     */
    public static function resolve(array $style): array
    {
        $name = $style['shape'] ?? config('filament-map.markers.shape', 'pin');
        $shape = $name === 'svg' ? MarkerSvg::shape($style['svg'] ?? null) : null;

        if ($shape === null) {
            $name = array_key_exists($name, self::BUILT_IN) ? $name : 'pin';
            $shape = MarkerSvg::shape(self::BUILT_IN[$name]);
        }

        [$defaultWidth, $defaultHeight] = self::DEFAULT_SIZES[$name] ?? ($shape['ratio'] >= 1
            ? [self::DEFAULT_SIZE, self::DEFAULT_SIZE / $shape['ratio']]
            : [self::DEFAULT_SIZE * $shape['ratio'], self::DEFAULT_SIZE]);

        $width = (float) ($style['size']['width'] ?? 0);
        $height = (float) ($style['size']['height'] ?? 0);

        if ($width > 0 && $height <= 0) {
            $height = $width / $shape['ratio'];
        } elseif ($height > 0 && $width <= 0) {
            $width = $height * $shape['ratio'];
        } elseif ($width <= 0) {
            [$width, $height] = [$defaultWidth, $defaultHeight];
        }

        return ['name' => $name, ...$shape, 'width' => round($width, 2), 'height' => round($height, 2)];
    }
}
