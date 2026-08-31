# Geopoints, apparence et événements

## Responsabilités

Le modèle est séparé en trois niveaux :

- `GeoPoint` porte la position et l’état de publication ;
- `GeoPointType` porte l’apparence par défaut ;
- la liaison carte / point porte la visibilité et les surcharges propres à une carte.

Le point ne connaît ni contenu narratif, ni déclencheur, ni action. Au clic, le
viewer publie `filament-map:point-clicked` avec la carte, le scope et le point.
`filament-orchestrator` peut transformer cet événement en
`map.point.clicked`, sélectionner un déclencheur et exécuter ses actions.

## Apparence

`GeoPointType::marker_style` contient une structure de rendu :

```php
[
    'shape' => 'pin',
    'svg' => null,
    'content' => [
        'type' => 'icon',
        'value' => 'heroicon-o-map-pin',
    ],
    'size' => ['width' => 36, 'height' => 48],
    'css' => [],
]
```

Pour `image`, la valeur provient d’abord de la collection Media Library du
point, puis de celle de son type. Un point et sa liaison à une carte peuvent
surcharger le style.

## Regroupement selon le zoom

Le regroupement appartient à la carte. La configuration initiale se trouve
dans `filament-map.clustering` et peut être surchargée dans
`Map::options['clustering']`.

Chaque point peut fournir `clusterable: false` ou un `cluster_group` dans ses
options. Pour de gros volumes, le contrat de payload peut être conservé tout
en déplaçant la requête par limites et zoom côté serveur.
