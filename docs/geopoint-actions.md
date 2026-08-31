# Geopoints, apparence et actions

## Responsabilites

Le modele est volontairement separe en quatre niveaux :

- `GeoPoint` porte la position, l'etat de publication et ses actions ;
- `GeoPointType` porte l'apparence par defaut ;
- la liaison carte / point porte la visibilite et les surcharges propres a une carte ;
- l'application parente porte le contenu metier et transforme ses evenements Laravel en evenements Livewire.

Le point ne connait donc ni un voyage, ni une etape, ni un contenu narratif.

## Actions declaratives

Une action associe un declencheur a un effet. Elle ne stocke jamais une classe
PHP a instancier ou une expression a executer.

Declencheurs disponibles :

- `load` ;
- `click` ;
- `double_click` ;
- `mouse_enter` ;
- `mouse_leave` ;
- `event`, avec un nom dans `trigger_event`.

Effets disponibles :

- `dispatch` : emettre un evenement navigateur et Livewire ;
- `show`, `hide`, `toggle` : changer la visibilite locale ;
- `open_popup`, `close_popup` ;
- `navigate` : ouvrir une URL ou demander une navigation a l'orchestrateur.

La table `filament_map_geo_point_actions` contient :

- `geo_point_id` ;
- `name` et `key` ;
- `trigger` et `trigger_event` ;
- `type`, `target` et `payload` ;
- `options`, `sort_order` et `is_active`.

La cle est unique pour un point. Elle permet a un orchestrateur de remplacer ou
de desactiver une action sans dependre de son identifiant de base de donnees.

### Exemples

Afficher un point lorsque le scenario active une etape :

```php
[
    'key' => 'show-on-step',
    'trigger' => 'event',
    'trigger_event' => 'trip.step.activated',
    'type' => 'show',
]
```

Emettre un evenement Livewire au clic :

```php
[
    'key' => 'select-place',
    'trigger' => 'click',
    'type' => 'dispatch',
    'target' => 'trip.place.selected',
    'payload' => ['source' => 'map'],
]
```

Ouvrir une navigation :

```php
[
    'key' => 'open-directions',
    'trigger' => 'click',
    'type' => 'navigate',
    'target' => '/directions/{pointId}',
    'options' => ['new_tab' => false],
]
```

Le payload de rendu expose la meme intention sous une forme stable :

```json
{
  "key": "show-on-step",
  "trigger": {
    "type": "event",
    "event": "trip.step.activated"
  },
  "effect": {
    "type": "show",
    "target": null,
    "payload": {}
  },
  "options": {}
}
```

## Evenements Laravel et Livewire

Un evenement Laravel serveur n'est pas directement visible dans le navigateur.
L'application parente choisit quels evenements metier doivent traverser cette
frontiere, puis les relaie avec Livewire :

```php
$this->dispatch(
    'trip.step.activated',
    scope: 'trip-map',
    stepId: $step->getKey(),
);
```

L'executeur cote carte ecoutera le nom public `trip.step.activated`, verifiera
le `scope`, puis appliquera l'effet au point. Dans l'autre sens, `dispatch`
emettra le nom public configure avec le contexte de la carte et du point.

Les noms publics doivent rester des identifiants fonctionnels. Il ne faut pas
exposer le nom complet d'une classe d'evenement Laravel dans le payload.

Le modele, le formulaire et le payload des actions sont en place. L'executeur
JavaScript des effets est l'etape suivante ; aucune navigation ni injection de
SVG arbitraire n'est executee automatiquement par ce premier socle.

## Apparence

`GeoPointType::marker_style` contient une structure de rendu :

```php
[
    'shape' => 'pin', // pin, circle, star ou svg
    'svg' => null,
    'content' => [
        'type' => 'icon', // none, icon, image ou text
        'value' => 'heroicon-o-map-pin',
    ],
    'size' => [
        'width' => 36,
        'height' => 48,
    ],
    'css' => [],
]
```

Pour `image`, la valeur provient d'abord de la collection Media Library du
point, puis de celle de son type. Un point et sa liaison a une carte peuvent
toujours surcharger le style, mais le type reste la source principale.

Les formes fournies par le plugin seront des SVG connus. Un SVG personnalise
devra etre nettoye avec une liste blanche avant d'etre injecte dans le DOM.

## Regroupement selon le zoom

Le regroupement appartient a la carte, pas au point. La configuration initiale
se trouve dans `filament-map.clustering` et peut etre surchargee dans
`Map::options['clustering']` :

```php
[
    'clustering' => [
        'enabled' => true,
        'max_zoom' => 14,
        'radius' => 80,
        'group_by_type' => false,
    ],
]
```

Chaque point peut exceptionnellement fournir `clusterable: false` ou un
`cluster_group` dans ses options. Des groupes differents ne doivent jamais
etre fusionnes.

Approche recommandee :

1. utiliser un index spatial client `Supercluster` pour les volumes petits et
   moyens ; il recalcule les groupes selon le zoom et les limites visibles ;
2. au clic sur un groupe, zoomer jusqu'a son niveau d'expansion ;
3. ne plus regrouper au-dessus de `max_zoom` ;
4. pour plusieurs dizaines de milliers de points, garder le meme contrat de
   payload mais deplacer la requete par limites et zoom cote serveur.

`Supercluster` est preferable a une dependance etroitement liee aux marqueurs
Leaflet : l'index spatial reste reutilisable si le moteur de rendu evolue.
