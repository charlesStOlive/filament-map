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

    /** La taille standard : le plus grand côté d'un marqueur à 100 %, en px (l'épingle mesure 30 × 40). */
    public const STANDARD_SIZE = 40;

    /** Les bornes de la taille, en % de la taille standard. */
    public const MIN_PERCENT = 20;

    public const MAX_PERCENT = 300;

    /**
     * Le décalage du marqueur par rapport à la position du point, au plus, en % de sa largeur (horizontal) ou de sa
     * hauteur (vertical) : il suit donc la taille.
     */
    public const MAX_OFFSET = 100;

    /**
     * La forme d'un style de marqueur, lue (voir MarkerSvg::shape()), avec son nom et sa taille en px : son plus grand
     * côté est la taille standard multipliée par `size` (un pourcentage, 100 par défaut), l'autre suit les proportions
     * de la forme. Un SVG personnalisé illisible laisse place à l'épingle.
     *
     * @param  array<string, mixed>  $style  Le `marker_style` d'un type ou d'un point.
     * Sa position : l'ancrage (le point de la forme posé sur la position : celui du style, sinon du SVG), le décalage
     * (`offset`, en % de la taille) et la rotation (`rotation`, en degrés, autour de l'ancrage).
     *
     * @return array{name: string, svg: string, ratio: float, slot: array<string, float|string>|null, anchor: string, width: float, height: float, percent: float, offset: array{x: float, y: float}, rotation: float}
     */
    public static function resolve(array $style): array
    {
        $name = $style['shape'] ?? config('filament-map.markers.shape', 'pin');
        $shape = $name === 'svg' ? MarkerSvg::shape($style['svg'] ?? null) : null;

        if ($shape === null) {
            $name = array_key_exists($name, self::BUILT_IN) ? $name : 'pin';
            $shape = MarkerSvg::shape(self::BUILT_IN[$name]);
        }

        $percent = self::percent($style['size'] ?? null, $shape['ratio']);
        $side = self::STANDARD_SIZE * $percent / 100;
        [$width, $height] = $shape['ratio'] >= 1 ? [$side, $side / $shape['ratio']] : [$side * $shape['ratio'], $side];

        return [
            'name' => $name,
            ...$shape,
            // L'ancrage réglé sur le type l'emporte sur celui du SVG (data-anchor).
            'anchor' => in_array($style['anchor'] ?? null, MarkerSvg::ANCHORS, true) ? $style['anchor'] : $shape['anchor'],
            'width' => round($width, 2),
            'height' => round($height, 2),
            'percent' => $percent,
            'offset' => [
                'x' => self::bounded($style['offset']['x'] ?? 0, -self::MAX_OFFSET, self::MAX_OFFSET),
                'y' => self::bounded($style['offset']['y'] ?? 0, -self::MAX_OFFSET, self::MAX_OFFSET),
            ],
            'rotation' => self::bounded($style['rotation'] ?? 0, -180, 180),
        ];
    }

    private static function bounded(mixed $value, float $min, float $max): float
    {
        return is_numeric($value) ? (float) max($min, min($max, round((float) $value, 2))) : 0.0;
    }

    /**
     * La taille en % : un nombre, borné. Une taille d'avant les pourcentages (`['width' => …, 'height' => …]` en px)
     * devient le pourcentage de son plus grand côté.
     */
    public static function percent(mixed $size, float $ratio = 1.0): float
    {
        if (is_array($size)) {
            $width = (float) ($size['width'] ?? 0);
            $height = (float) ($size['height'] ?? 0);
            $side = max($width, $height, $width > 0 && $height <= 0 ? $width / $ratio : 0, $height > 0 && $width <= 0 ? $height * $ratio : 0);
            $size = $side > 0 ? 100 * $side / self::STANDARD_SIZE : null;
        }

        if (! is_numeric($size)) {
            return 100.0;
        }

        return (float) max(self::MIN_PERCENT, min(self::MAX_PERCENT, round((float) $size)));
    }
}
