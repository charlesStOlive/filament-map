# filament-map

Plugin Filament de stockage et de rendu de cartes, couches et points.

## Documentation intégrée à Filament

Le package dépend de `guava/filament-knowledge-base` et embarque une documentation opérateur dans `docs/knowledge-base`.
L'application hôte peut l'ajouter à sa base de connaissances avec :

```bash
php artisan vendor:publish --tag=filament-map-docs --force
```

Les ressources Cartes, Couches cartographiques, Points géographiques et Types de points implémentent `HasKnowledgeBase`. Le plugin compagnon Guava affiche donc automatiquement les articles correspondants dans leur menu d'aide.

## Responsabilité

`filament-map` reste un moteur cartographique bas niveau :

- il stocke `Map`, `MapScene`, `MapLayer`, `GeoPoint` et `GeoPointType` ;
- il construit un payload de rendu ;
- il publie les clics sur les points et les éléments GeoJSON ;
- il exécute des commandes de carte via un registre JavaScript extensible.

Il ne stocke plus d’actions sur `GeoPoint`. Les scénarios, déclencheurs et
séquences d’actions appartiennent à `filament-orchestrator`.

## Cluster Filament

Par défaut, les ressources sont rangées dans le cluster `Cartographie`. Une
application peut fournir son propre cluster ou désactiver le regroupement :

```php
FilamentMapPlugin::make()->cluster(Voyage::class);
FilamentMapPlugin::make()->cluster(null);
```

## Installation

```bash
composer require charlesstolive/filament-map
php artisan filament-map:install
php artisan migrate
```

## Scènes cartographiques

`MapScene` assemble une carte et les couches de la bibliothèque, avec ordre, visibilité initiale et styles locaux. Les limites de zoom restent celles de la carte. Les points sont fournis par le scénario consommateur et ne sont pas stockés dans la scène.

```blade
<livewire:filament-map-viewer :scene="$scene" :points="$points" event-scope="voyage" />
```

En mode scène, fournir ou remplacer des couches externes est refusé. Les anciennes relations directes carte/couche et l’API `map:` sont conservées pour les consommateurs existants ; l’interface de composition utilise désormais les scènes.

Voir [les scènes cartographiques](docs/knowledge-base/map/scenes.md).

## Saisir une position : `CoordinatesInput`

Un seul composant de formulaire pour une paire latitude / longitude, sur une
seule ligne : deux champs numériques bornés (−90…90, −180…180) fusionnés sous un
libellé, la règle « les deux ou aucune » (`required()` les exige tous les
deux), et un unique bouton icône (carte + repère, libellé en infobulle) qui
ouvre la carte dans un popup. Cliquer sur la carte ou déplacer le repère met le
formulaire à jour en direct ; « Terminé » referme le popup.

Selon le nom donné à `make()`, la position se stocke dans un JSON ou dans deux
colonnes SQL :

```php
// Deux colonnes SQL (frères du composant) : `latitude` / `longitude` par défaut…
CoordinatesInput::make()->required();
// … ou nommées à la demande.
CoordinatesInput::make()->latitudeField('center_latitude')->longitudeField('center_longitude');

// Un JSON : l'état est ['latitude' => …, 'longitude' => …] sous la clé `position`
// (colonne castée en array, ou clé d'un tableau JSON).
CoordinatesInput::make('position');
```

Options : `->label()` / `->hiddenLabel()` (« Coordonnées » par défaut),
`->scene($scene)` (couches de la carte ; sans scène connue, ni bouton ni
carte), `->viewport('zoom')` (le centre **et** le zoom suivent la carte, le
champ de zoom reste un champ frère), `->initialZoom()`, `->mapHeight()`,
`->withoutMap()` (saisie seule), `->live(onBlur: true)`.

Il remplace l'ancien `CoordinatePicker` (dont le bouton n'était écouté par
personne). `MapViewportPicker` reste disponible seul, et porte toujours
`captureZoomAction()` pour les champs de zoom.

## Viewer Livewire

```blade
<livewire:filament-map-viewer :map="$map" event-scope="trip-map" :fit-bounds="true" />
```

Le viewer publie notamment :

- `filament-map:point-clicked` ;
- `filament-map:point-hovered` ;
- `filament-map:feature-clicked` ;
- `filament-map:coordinates-picked`.

Les commandes intégrées sont :

- `show-layer`, `hide-layer`, `toggle-layer` ;
- `zoom-to`, `move-to`, `fit-bounds` ;
- `highlight-feature`.

Une application peut enregistrer une commande avec
`window.FilamentMap.registerCommand(name, handler)`. Si son bundle est chargé
avant celui du plugin, elle peut attendre l’événement `filament-map:ready`.

Voir [la documentation du viewer](docs/livewire-map-viewer.md) et [le contrat
des points](docs/geopoints.md).
