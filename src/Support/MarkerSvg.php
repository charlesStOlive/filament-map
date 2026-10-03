<?php

namespace CharlesStOlive\FilamentMap\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

/**
 * Le SVG qu'un marqueur dessine dans le navigateur, préparé côté serveur : l'icône d'un type (son nom, par exemple
 * `heroicon-o-map-pin`, devient son dessin) et la forme SVG personnalisée d'un type, nettoyée.
 *
 * Rien n'y lève d'erreur : une icône inconnue ou un SVG illisible donnent null, et le marqueur se rabat sur sa forme
 * par défaut.
 */
final class MarkerSvg
{
    /** Ce qu'un SVG de marqueur ne garde jamais : ce qui exécute, charge ou embarque autre chose qu'un dessin. */
    private const FORBIDDEN_ELEMENTS = ['script', 'foreignObject', 'iframe', 'object', 'embed', 'audio', 'video', 'handler', 'listener'];

    /** @var array<string, string|null> */
    private static array $icons = [];

    /** Le dessin d'une icône Blade Icons, ou null quand le nom n'en désigne aucune. */
    public static function icon(?string $name): ?string
    {
        if (blank($name) || ! function_exists('svg')) {
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
     * Un SVG saisi à la main, débarrassé de ce qui n'est pas du dessin : scripts, contenus embarqués, attributs
     * `on…` et liens `javascript:`. Null quand ce n'est pas un SVG lisible.
     */
    public static function sanitize(?string $svg): ?string
    {
        if (blank($svg)) {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML(trim($svg), LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;

        if (! $loaded || ! $root instanceof DOMElement || strtolower($root->localName) !== 'svg') {
            return null;
        }

        $xpath = new DOMXPath($document);

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

        return $document->saveXML($root) ?: null;
    }
}
