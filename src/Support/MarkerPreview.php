<?php

namespace CharlesStOlive\FilamentMap\Support;

use CharlesStOlive\FilamentMap\Services\MapPayloadBuilder;

/**
 * L'aperçu d'un type de point (formulaire et liste des types) : son apparence telle que la carte la dessinera, avec
 * chaque image d'exemple quand il en accepte une, et ce qu'il montre réellement — avec la raison quand un réglage ne
 * produit pas l'effet attendu (pas de zone de contenu, icône introuvable, SVG illisible).
 *
 * Les apparences viennent de MapPayloadBuilder::appearance(), comme sur la carte : l'aperçu ne dessine rien d'autre.
 */
final class MarkerPreview
{
    /**
     * Des images d'exemple de chaque format, pour voir comment une image se recadre dans la zone de contenu : un repère
     * à chaque bord (le soleil à gauche et la tour à droite du paysage, le soleil en haut et l'eau en bas du portrait).
     *
     * @var array<string, array{label: string, svg: string}>
     */
    public const SAMPLES = [
        'square' => [
            'label' => 'Carrée',
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" fill="#e9f3e6"/><circle cx="60" cy="60" r="34" fill="#f2b84b"/><circle cx="48" cy="52" r="5" fill="#333"/><circle cx="72" cy="52" r="5" fill="#333"/><path d="M44 72q16 14 32 0" stroke="#333" stroke-width="4" fill="none"/></svg>',
        ],
        'landscape' => [
            'label' => 'Paysage',
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 90"><rect width="160" height="90" fill="#9fd3f5"/><circle cx="22" cy="22" r="12" fill="#ffcf3f"/><path d="M0 70 40 38 70 62 105 30 160 72V90H0Z" fill="#5b8f4e"/><path d="M0 80h160v10H0Z" fill="#3d6b35"/><rect x="142" y="44" width="10" height="30" fill="#c0392b"/></svg>',
        ],
        'portrait' => [
            'label' => 'Portrait',
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 90 160"><rect width="90" height="160" fill="#f6d7a7"/><circle cx="45" cy="16" r="10" fill="#e8743b"/><rect x="38" y="44" width="14" height="92" fill="#fff" stroke="#c0392b" stroke-width="3"/><path d="M33 44h24l-12-14Z" fill="#c0392b"/><path d="M0 136h90v24H0Z" fill="#2e6f9e"/></svg>',
        ],
    ];

    /**
     * @param  array<string, mixed>  $style  Le `marker_style` du type.
     * @param  string|null  $typeImage  L'image par défaut du type, proposée comme exemple quand il en a une.
     * @return array{status: array{label: string, color: string, detail: string}, acceptsImage: bool, samples: array<string, string>, appearances: array<string, array<string, mixed>>, default: string, anchor: string, size: array{width: float, height: float}}
     */
    public static function for(array $style, ?string $icon = null, ?string $color = null, ?string $typeImage = null): array
    {
        $builder = app(MapPayloadBuilder::class);
        $shape = MarkerShapes::resolve($style);
        $declared = $style['content']['type'] ?? config('filament-map.markers.content_type', 'icon');
        $acceptsImage = $declared === 'image' && $shape['slot'] !== null;

        $samples = $acceptsImage
            ? [
                ...array_map(fn (array $sample): string => $sample['label'], self::SAMPLES),
                ...(filled($typeImage) ? ['type' => 'Image du type'] : []),
                'none' => 'Aucune',
            ]
            : ['none' => ''];

        $appearances = [];

        foreach (array_keys($samples) as $key) {
            $image = match ($key) {
                'none' => null,
                'type' => $typeImage,
                default => 'data:image/svg+xml;base64,'.base64_encode(self::SAMPLES[$key]['svg']),
            };
            $appearances[$key] = $builder->appearance($style, $icon, $image, $color);
        }

        $default = $acceptsImage ? 'square' : 'none';

        return [
            'status' => self::status($style, $declared, $shape, $appearances['none']),
            'acceptsImage' => $acceptsImage,
            'samples' => $acceptsImage ? $samples : [],
            'appearances' => $appearances,
            'default' => $default,
            'anchor' => $shape['anchor'],
            'size' => ['width' => $shape['width'], 'height' => $shape['height']],
        ];
    }

    /** Les mots de l'ancrage, pour l'aperçu. */
    public static function anchorLabel(string $anchor): string
    {
        return [
            'center' => 'au centre',
            'top' => 'en haut',
            'bottom' => 'en bas (la pointe)',
            'left' => 'à gauche',
            'right' => 'à droite',
            'top-left' => 'en haut à gauche',
            'top-right' => 'en haut à droite',
            'bottom-left' => 'en bas à gauche',
            'bottom-right' => 'en bas à droite',
        ][$anchor] ?? $anchor;
    }

    /**
     * @param  array<string, mixed>  $shape
     * @param  array<string, mixed>  $withoutImage  L'apparence sans image : ce que le point montre à défaut.
     * @return array{label: string, color: string, detail: string}
     */
    private static function status(array $style, string $declared, array $shape, array $withoutImage): array
    {
        $unreadable = ($style['shape'] ?? null) === 'svg' && $shape['name'] !== 'svg';
        $warning = fn (string $label, string $detail): array => ['label' => $label, 'color' => 'warning', 'detail' => $detail];

        if ($unreadable) {
            return $warning('SVG illisible', 'Le SVG personnalisé n’a pas pu être lu (vide, ou pas un SVG) : la carte montre l’épingle à sa place.');
        }

        if ($declared !== 'none' && $shape['slot'] === null) {
            return $warning('Forme seule', 'Le contenu demandé ne peut pas s’afficher : la forme n’a pas de zone de contenu. Marquez un circle, une ellipse ou un rect du SVG avec data-slot.');
        }

        return match ($declared) {
            'image' => [
                'label' => 'Accepte une image',
                'color' => 'success',
                'detail' => 'L’image se pose dans la zone en pointillés et y est recadrée au centre. Sans image, le point montre '
                    .($withoutImage['content']['type'] === 'icon' ? 'son icône.' : 'sa forme seule (pas d’icône).'),
            ],
            'icon' => $withoutImage['content']['type'] === 'icon'
                ? ['label' => 'Icône', 'color' => 'gray', 'detail' => 'Le point montre une icône. Il ignore les images qu’on lui propose.']
                : $warning('Icône introuvable', 'Aucune icône ne porte ce nom : la forme reste seule.'),
            'text' => $withoutImage['content']['type'] === 'text'
                ? ['label' => 'Texte', 'color' => 'gray', 'detail' => 'Le point montre ce texte. Il ignore les images qu’on lui propose.']
                : $warning('Texte vide', 'Saisissez le texte à montrer : en attendant, la forme reste seule.'),
            default => ['label' => 'Forme seule', 'color' => 'gray', 'detail' => 'Le point ne montre que sa forme. Il ignore les images qu’on lui propose.'],
        };
    }
}
