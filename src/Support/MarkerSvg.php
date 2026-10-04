<?php

namespace CharlesStOlive\FilamentMap\Support;

use CharlesStOlive\FilamentMap\Models\GeoPointType;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

/**
 * Le SVG qu'un marqueur dessine dans le navigateur, préparé côté serveur : l'icône d'un type (son nom, par exemple
 * `heroicon-o-map-pin`, devient son dessin) et la forme d'un type, nettoyée et lue.
 *
 * Une forme est un SVG qui peut désigner :
 *
 * - sa **zone de contenu**, où se posent l'image, l'icône ou le texte du point : un `circle`, une `ellipse` ou un `rect`
 *   marqué `data-slot` (un attribut, pas un id : une carte montre des dizaines de marqueurs, un id y serait en double).
 *   Elle n'est pas dessinée ; sans elle, la forme ne montre rien d'autre qu'elle-même ;
 * - son **point d'ancrage**, posé sur la position du point : `data-anchor` sur la racine (`bottom` pour une épingle ;
 *   `center` par défaut).
 *
 * Rien n'y lève d'erreur : une icône inconnue ou un SVG illisible donnent null.
 */
final class MarkerSvg
{
    /** Ce qu'un SVG de marqueur ne garde jamais : ce qui exécute, charge ou embarque autre chose qu'un dessin. */
    private const FORBIDDEN_ELEMENTS = ['script', 'foreignObject', 'iframe', 'object', 'embed', 'audio', 'video', 'handler', 'listener'];

    /** Les ancrages de MapLibre. */
    public const ANCHORS = ['center', 'top', 'bottom', 'left', 'right', 'top-left', 'top-right', 'bottom-left', 'bottom-right'];

    /** @var array<string, string|null> */
    private static array $icons = [];

    /** @var array<string, array<string, mixed>|null> */
    private static array $shapes = [];

    /** Le nom d'une icône qui est le dessin d'un type de point : `type:<clé du type>`. */
    public const TYPE_ICON_PREFIX = 'type:';

    /**
     * Le dessin d'une icône, ou null quand le nom n'en désigne aucune : une icône Blade Icons
     * (`heroicon-o-map-pin`), ou le dessin d'un type de point (`type:<clé>`, voir typeDrawing()).
     */
    public static function icon(?string $name): ?string
    {
        if (blank($name)) {
            return null;
        }

        // Relu à chaque fois (pas de cache) : le dessin d'un type peut changer, et ce n'est qu'une requête.
        if (str_starts_with($name, self::TYPE_ICON_PREFIX)) {
            $type = GeoPointType::query()->where('key', substr($name, strlen(self::TYPE_ICON_PREFIX)))->first();

            return $type === null ? null : self::typeDrawing($type);
        }

        if (! function_exists('svg')) {
            return null;
        }

        return self::$icons[$name] ??= (function () use ($name): ?string {
            try {
                return svg($name)->toHtml();
            } catch (Throwable) {
                return null;
            }
        })();
    }

    /**
     * Le dessin d'un type de point, pour servir d'icône dans la zone d'un autre marqueur : sa forme SVG personnalisée,
     * nettoyée, sans sa zone de contenu, et monochrome comme une icône (monochrome()) — elle prend la couleur de
     * l'icône, et s'inverse avec elle. Null pour une forme fournie (épingle, cercle, étoile) ou un SVG illisible.
     */
    public static function typeDrawing(GeoPointType $type): ?string
    {
        $style = $type->marker_style ?? [];
        $svg = ($style['shape'] ?? null) === 'svg' ? (self::shape($style['svg'] ?? null)['svg'] ?? null) : null;

        return $svg === null ? null : self::monochrome($svg);
    }

    /**
     * Un dessin d'une seule couleur, celle du texte (`currentColor`) : chaque remplissage et chaque trait qui ne sont pas
     * `none` la prennent — attributs `fill` et `stroke`, et dans `style`. Un SVG qu'on ne peut pas relire reste tel quel.
     */
    public static function monochrome(string $svg): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($svg, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded || ! $document->documentElement instanceof DOMElement) {
            return $svg;
        }

        $keeps = fn (string $value): bool => in_array(strtolower(trim($value)), ['none', 'transparent', 'currentcolor', ''], true);

        foreach (iterator_to_array((new DOMXPath($document))->query('//*')) as $element) {
            foreach (['fill', 'stroke'] as $attribute) {
                if ($element->hasAttribute($attribute) && ! $keeps($element->getAttribute($attribute))) {
                    $element->setAttribute($attribute, 'currentColor');
                }
            }

            if ($element->hasAttribute('style')) {
                $element->setAttribute('style', preg_replace_callback(
                    '/(^|;)\s*(fill|stroke)\s*:\s*([^;]+)/i',
                    fn (array $match): string => $keeps($match[3]) ? $match[0] : $match[1].$match[2].':currentColor',
                    $element->getAttribute('style'),
                ));
            }
        }

        return $document->saveXML($document->documentElement) ?: $svg;
    }

    /**
     * Un SVG saisi à la main, débarrassé de ce qui n'est pas du dessin : scripts, contenus embarqués, attributs
     * `on…` et liens `javascript:`. Null quand ce n'est pas un SVG lisible.
     */
    public static function sanitize(?string $svg): ?string
    {
        return self::shape($svg)['svg'] ?? null;
    }

    /**
     * Une forme lue : son SVG nettoyé et sans sa zone de contenu (`svg`), ses proportions (`ratio`, largeur sur
     * hauteur), sa zone de contenu en % de la forme (`slot` : left, top, width, height, radius — ou null) et son
     * ancrage (`anchor`). Null quand ce n'est pas un SVG lisible.
     *
     * @return array{svg: string, ratio: float, slot: array{left: float, top: float, width: float, height: float, radius: string}|null, anchor: string}|null
     */
    public static function shape(?string $svg): ?array
    {
        if (blank($svg)) {
            return null;
        }

        return self::$shapes[md5($svg)] ??= self::read($svg);
    }

    private static function read(string $svg): ?array
    {
        // `<circle data-slot …>`, tel qu'on l'écrit en HTML, n'est pas du XML : on lui donne une valeur.
        $svg = preg_replace('/(\sdata-(?:slot|anchor))(?=[\s>\/])/i', '$1=""', trim($svg));

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($svg, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;

        if (! $loaded || ! $root instanceof DOMElement || strtolower($root->localName) !== 'svg') {
            return null;
        }

        $xpath = new DOMXPath($document);
        self::clean($xpath);

        $box = self::viewBox($root);
        $slotElement = $xpath->query('//*[@data-slot]')->item(0);
        $slot = $slotElement instanceof DOMElement && $box !== null ? self::slot($slotElement, $box) : null;

        foreach (iterator_to_array($xpath->query('//*[@data-slot]')) as $node) {
            $node->parentNode?->removeChild($node);
        }

        $anchor = strtolower(trim($root->getAttribute('data-anchor')));

        return [
            'svg' => $document->saveXML($root) ?: '',
            'ratio' => $box === null ? 1.0 : $box[2] / $box[3],
            'slot' => $slot,
            'anchor' => in_array($anchor, self::ANCHORS, true) ? $anchor : 'center',
        ];
    }

    private static function clean(DOMXPath $xpath): void
    {
        foreach (self::FORBIDDEN_ELEMENTS as $name) {
            foreach (iterator_to_array($xpath->query("//*[local-name()='{$name}']")) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        foreach (iterator_to_array($xpath->query('//*')) as $element) {
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = strtolower(preg_replace('/\s+/', '', $attribute->nodeValue ?? ''));

                if (str_starts_with($name, 'on') || (str_ends_with($name, 'href') && ! str_starts_with($value, '#'))) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }
    }

    /** @return array{0: float, 1: float, 2: float, 3: float}|null La viewBox (x, y, largeur, hauteur), à défaut width et height. */
    private static function viewBox(DOMElement $root): ?array
    {
        $values = array_map('floatval', preg_split('/[\s,]+/', trim($root->getAttribute('viewBox'))) ?: []);

        if (count($values) === 4 && $values[2] > 0 && $values[3] > 0) {
            return $values;
        }

        $width = (float) $root->getAttribute('width');
        $height = (float) $root->getAttribute('height');

        return $width > 0 && $height > 0 ? [0.0, 0.0, $width, $height] : null;
    }

    /**
     * La zone de contenu, en % de la viewBox, avec son arrondi CSS. Un élément transformé (`transform`) est lu sans sa
     * transformation.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $box
     * @return array{left: float, top: float, width: float, height: float, radius: string}|null
     */
    private static function slot(DOMElement $element, array $box): ?array
    {
        $number = fn (string $name): float => (float) $element->getAttribute($name);

        [$x, $y, $width, $height, $radius] = match (strtolower($element->localName)) {
            'circle' => [$number('cx') - $number('r'), $number('cy') - $number('r'), 2 * $number('r'), 2 * $number('r'), '50%'],
            'ellipse' => [$number('cx') - $number('rx'), $number('cy') - $number('ry'), 2 * $number('rx'), 2 * $number('ry'), '50%'],
            'rect' => [$number('x'), $number('y'), $number('width'), $number('height'), null],
            default => [0, 0, 0, 0, null],
        };

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        if ($radius === null) {
            $rx = $element->hasAttribute('rx') ? $number('rx') : $number('ry');
            $ry = $element->hasAttribute('ry') ? $number('ry') : $rx;
            $radius = round(min(50, 100 * $rx / $width), 3).'% / '.round(min(50, 100 * $ry / $height), 3).'%';
        }

        $percent = fn (float $value, float $total): float => round(100 * $value / $total, 3);

        return [
            'left' => $percent($x - $box[0], $box[2]),
            'top' => $percent($y - $box[1], $box[3]),
            'width' => $percent($width, $box[2]),
            'height' => $percent($height, $box[3]),
            'radius' => $radius,
        ];
    }
}
