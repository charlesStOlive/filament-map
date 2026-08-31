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

- il stocke `Map`, `MapLayer`, `GeoPoint` et `GeoPointType` ;
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

## Viewer Livewire

```blade
<livewire:filament-map-viewer :map="$map" event-scope="trip-map" :fit-bounds="true" />
```

Le viewer publie notamment :

- `filament-map:point-clicked` ;
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
