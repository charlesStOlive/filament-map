# Conception du plugin Filament Map

## Objectif

Le plugin doit fournir une base cartographique reutilisable pour Laravel et Filament.

Il doit fonctionner seul, avec ses propres tables, ressources Filament et composant Livewire, tout en restant orchestrable par une application parente.

Exemple d'usage futur : une application possede un modele `Voyage` qui choisit une carte, active ou masque des points selon un scenario, ajoute des contenus metier, puis surcharge certaines options d'affichage du plugin.

## Decisions de base

- Base de donnees cible : MySQL / MariaDB.
- Le plugin fournit ses propres migrations, modeles et tables pivot.
- Le plugin peut fonctionner sans orchestrateur parent.
- Le rendu prioritaire est une carte Leaflet basee sur des couches GeoJSON stylisees.
- Les points geographiques restent stockes une seule fois et peuvent etre affiches sur plusieurs cartes.
- Les images associees aux types ou aux points passent par Spatie Media Library.
- Les icones peuvent venir du systeme disponible dans l'application parente.
- Les ressources Filament sont regroupees dans un cluster configurable.
- Les options globales runtime pourront utiliser Spatie Settings.

## Concepts metier

### Carte

Une carte represente une configuration d'affichage geographique.

Elle peut etre utilisee seule dans Filament, affichee dans un composant Livewire, ou orchestree par un modele externe.

Une carte contient :

- un nom ;
- un slug ;
- un mode principal ;
- un centre initial ;
- un niveau de zoom initial ;
- des limites geographiques optionnelles ;
- des options d'affichage ;
- des couches ;
- des points attaches.

Le mode principal ne doit pas enfermer l'architecture. Une carte peut etre principalement `geojson`, mais contenir aussi une couche de tuiles OpenStreetMap, une couche de points et plus tard une couche SVG.


### Couche de carte

Une couche est un element affichable dans une carte.

Types prevus :

- `geojson` : contours, regions, rivieres, zones d'intervention, parcours ;
- `tile` : fond OpenStreetMap ou fournisseur compatible ;
- `points` : groupe de points geographiques ;
- `svg_overlay` : carte SVG calee sur des bornes geographiques ;
- `custom` : extension future.

Chaque couche possede :

- une carte parente ;
- un type ;
- un nom ;
- une cle stable ;
- un ordre ;
- une visibilite par defaut ;
- une source ;
- un style ;
- des options.

Pour GeoJSON, la source pourra etre :

- un fichier gere par Spatie Media Library ;
- une URL ;
- un chemin local publie ;
- un JSON stocke en base pour les petits jeux de donnees.

### Point geographique

Un point geographique est une position reusable.

Il contient au minimum :

- un nom ;
- un slug ;
- une latitude ;
- une longitude ;
- une colonne spatiale MySQL / MariaDB lorsque disponible ;
- un type de point ;
- une icone ou image surchargeable ;
- des options ;
- un statut.

Le plugin doit garder le point volontairement generique. Il ne doit pas presumer qu'un point est une etape, une photo, un lieu touristique ou un contenu narratif. Ces usages seront portes par les types, les options et les orchestrateurs externes.

### Type de point

Un type de point permet de donner une signification et une apparence par defaut.

Exemples :

- lieu ;
- photo ;
- etape ;
- video ;
- hebergement ;
- zone d'interet.

Chaque type peut fournir :

- un nom ;
- une cle ;
- une couleur ;
- une icone ;
- une image par defaut via Media Library ;
- un style de marqueur ;
- des options ;
- une configuration de rendu par defaut.

Un point pourra surcharger l'apparence heritee de son type.

### Liaison carte / point

Un point peut appartenir a plusieurs cartes.

La table pivot doit porter des informations d'affichage propres a une carte :

- ordre ;
- visible par defaut ;
- couche cible optionnelle ;
- icone surchargee ;
- image surchargee ;
- style surcharge ;
- options surchargees ;
- contenu de popup/tooltip simple ;
- meta donnees d'orchestration.

Cela permet d'utiliser le meme point dans plusieurs cartes avec des rendus differents.

## Schema de donnees propose

Les noms exacts resteront configurables par prefixe si necessaire, mais la V1 peut partir sur un prefixe `filament_map_`.

### `filament_map_maps`

Champs principaux :

- `id`
- `name`
- `slug`
- `description`
- `mode`
- `center_latitude`
- `center_longitude`
- `zoom`
- `min_zoom`
- `max_zoom`
- `bounds`
- `options`
- `is_active`
- timestamps
- soft deletes optionnels

`bounds` peut etre stocke en JSON sous cette forme :

```json
{
  "southWest": { "lat": 10.35, "lng": 102.30 },
  "northEast": { "lat": 14.70, "lng": 107.65 }
}
```

### `filament_map_layers`

Champs principaux :

- `id`
- `map_id`
- `name`
- `key`
- `type`
- `source_type`
- `source_url`
- `source_path`
- `source_json`
- `style`
- `style_rules`
- `options`
- `sort_order`
- `is_visible_by_default`
- `is_active`
- timestamps

`style` definit le style global de la couche.

`style_rules` permet de styliser des features GeoJSON selon leurs proprietes.

Exemple :

```json
[
  {
    "when": { "property": "kind", "equals": "river" },
    "style": { "color": "#2563eb", "weight": 2, "opacity": 0.8 }
  },
  {
    "when": { "property": "kind", "equals": "province" },
    "style": { "color": "#64748b", "fillOpacity": 0.08 }
  }
]
```

### `filament_map_geo_point_types`

Champs principaux :

- `id`
- `name`
- `key`
- `description`
- `icon`
- `color`
- `marker_style`
- `options`
- `sort_order`
- `is_active`
- timestamps

Les images par defaut sont gerees via Spatie Media Library, par exemple avec une collection `default_marker_image`.

### `filament_map_geo_points`

Champs principaux :

- `id`
- `geo_point_type_id`
- `name`
- `slug`
- `description`
- `latitude`
- `longitude`
- `coordinates`
- `marker_style`
- `options`
- `is_active`
- timestamps
- soft deletes optionnels

Pour MySQL / MariaDB, l'intention est d'utiliser les types spatiaux exposes par Laravel, avec SRID 4326 pour les coordonnees GPS.

La strategie recommandee est double :

- garder `latitude` et `longitude` en colonnes decimales pour les formulaires, les tris simples, les imports et la lisibilite ;
- ajouter une colonne spatiale `coordinates` de type point avec SRID 4326 lorsque le moteur de base le supporte correctement.

Cela donne une base pratique pour Filament tout en permettant des recherches spatiales plus propres ensuite.

### `filament_map_geo_map_point`

Pivot entre cartes et points.

Champs principaux :

- `id`
- `map_id`
- `geo_point_id`
- `layer_id` nullable
- `sort_order`
- `is_visible_by_default`
- `label`
- `tooltip`
- `popup_content`
- `marker_style`
- `options`
- timestamps

Cette table est importante car une meme position peut etre affichee differemment selon la carte.

## GeoJSON stylise

Le GeoJSON stylise est probablement le meilleur compromis pour le plugin.

Il permet de garder une vraie logique geographique tout en produisant une carte visuellement controlee.

Usages prevus :

- contour d'un pays ;
- provinces ;
- rivieres ;
- zones d'intervention ;
- parcours ;
- polygones personnalises ;
- zones cliquables.

Principe :

1. Le plugin charge une couche GeoJSON.
2. Chaque feature est affichee par Leaflet via `L.geoJSON`.
3. Le style global vient de la couche.
4. Les styles conditionnels viennent de `style_rules`.
5. Les interactions viennent des options de couche.

Exemple de payload envoye au JavaScript :

```json
{
  "type": "geojson",
  "key": "cambodia-provinces",
  "visible": true,
  "source": {
    "type": "url",
    "url": "/storage/filament-map/cambodia-provinces.geojson"
  },
  "style": {
    "color": "#334155",
    "weight": 1,
    "fillColor": "#e2e8f0",
    "fillOpacity": 0.18
  },
  "styleRules": []
}
```

Cette approche remplace avantageusement un SVG dessine a la main quand la precision des coordonnees compte.

Le SVG overlay reste utile plus tard pour des cartes editoriales tres stylisees, mais il ne doit pas etre le socle principal de la V1.

## Composant Livewire

Le composant Livewire doit etre le point d'entree public du rendu de carte.

Nom envisage : `FilamentMapViewer` ou `MapViewer`.

Responsabilites du composant :

- recevoir une carte ;
- charger ses couches visibles ;
- charger ses points visibles ;
- appliquer les options globales ;
- appliquer les overrides fournis par un orchestrateur ;
- produire un payload JSON stable ;
- declencher l'initialisation JavaScript ;
- reagir aux evenements de selection ou de mise a jour.

Exemple d'utilisation Blade :

```blade
<livewire:filament-map-viewer
    :map="$map"
    height="h-[600px]"
    width="w-full"
/>
```

Exemple avec overrides :

```blade
<livewire:filament-map-viewer
    :map="$voyage->map"
    :points="$voyage->visibleMapPoints()"
    :options="$voyage->mapOptions()"
    height="h-full"
    width="w-full"
/>
```

Parametres prevus :

- `map` ou `mapId`
- `points`
- `layers`
- `options`
- `height`
- `width`
- `class`
- `interactive`
- `showControls`
- `fitBounds`
- `selectedPointId`

Les parametres `height`, `width` et `class` doivent rester des classes CSS pass-through. Le plugin ne doit pas essayer de deviner toutes les tailles possibles. L'application parente pourra donc utiliser Tailwind librement :

- `h-[500px]`
- `h-screen`
- `h-full`
- `w-full`
- `max-w-5xl`

Le conteneur Leaflet devra etre dans une zone `wire:ignore` pour eviter que Livewire ne detruise le DOM controle par Leaflet a chaque rendu.

## JavaScript propre

Le JavaScript doit rester separe du PHP et de Livewire.

Objectifs :

- pas de gros scripts inline ;
- pas de logique metier dans le JavaScript ;
- un payload JSON stable envoye par PHP ;
- un gestionnaire d'instances Leaflet ;
- des adapters par type de couche ;
- destruction propre des cartes lors des navigations Livewire / Filament ;
- evenements explicites pour les interactions.

Structure envisagee :

```text
resources/js/
  index.js
  map-manager.js
  leaflet-map-instance.js
  layers/
    tile-layer.js
    geojson-layer.js
    marker-layer.js
    svg-overlay-layer.js
  controls/
    layer-control.js
    point-picker-control.js
```

Le manager JavaScript doit :

- creer une carte si elle n'existe pas ;
- reutiliser une instance si possible ;
- appliquer les mises a jour sans tout reconstruire quand c'est raisonnable ;
- supprimer proprement l'instance quand le composant disparait ;
- exposer une petite API interne.

Evenements utiles :

- `filament-map:init`
- `filament-map:update`
- `filament-map:destroy`
- `filament-map:point-clicked`
- `filament-map:feature-clicked`
- `filament-map:coordinates-picked`
- `filament-map:bounds-changed`

Livewire envoie l'etat. JavaScript affiche. Les interactions remontent via des evenements.

## Selection d'un point depuis une carte

Les ressources Filament doivent permettre deux modes de saisie pour un point :

1. saisie manuelle des coordonnees ;
2. choix visuel depuis une carte cible.

Dans la ressource `GeoPointResource`, le formulaire devra contenir :

- champs latitude / longitude ;
- bouton ou action `Choisir sur la carte` ;
- modal avec une carte Leaflet ;
- choix de la carte cible utilisee comme fond ;
- clic sur la carte pour remplir latitude / longitude ;
- marqueur de previsualisation ;
- validation avant enregistrement.

Le picker doit pouvoir fonctionner avec :

- une carte GeoJSON stylisee ;
- un fond OpenStreetMap ;
- une carte hybride ;
- plus tard un SVG overlay si necessaire.

Workflow :

1. L'utilisateur ouvre la ressource point.
2. Il choisit un type de point.
3. Il entre les coordonnees a la main ou ouvre le picker.
4. Dans le picker, il selectionne une carte cible.
5. Il clique a l'endroit voulu.
6. Le plugin remplit latitude et longitude.
7. L'utilisateur ajuste si besoin.
8. Il sauvegarde.

Pour une couche GeoJSON, on pourra plus tard ajouter des aides :

- cliquer une feature pour utiliser son centre ;
- afficher les proprietes de la feature ;
- limiter la saisie a une zone ;
- snapper sur un parcours.

Ces aides ne sont pas necessaires en V1, mais l'architecture du picker doit les permettre.

## Ressources Filament

Toutes les ressources seront rangees dans un cluster configurable.

Ressources prevues :

- `MapResource`
- `MapLayerResource` ou relation manager dans `MapResource`
- `GeoPointResource`
- `GeoPointTypeResource`

### Cluster

Le plugin doit publier une configuration permettant de definir :

- le nom du cluster ;
- le slug ;
- l'icone ;
- le groupe de navigation ;
- le tri ;
- les labels ;
- les chemins ;
- l'activation/desactivation des ressources fournies.

Exemple de configuration :

```php
return [
    'cluster' => [
        'enabled' => true,
        'label' => 'Cartographie',
        'slug' => 'maps',
        'icon' => 'heroicon-o-map',
    ],

    'resources' => [
        'maps' => true,
        'layers' => true,
        'geo_points' => true,
        'geo_point_types' => true,
    ],
];
```

Les applications parentes doivent pouvoir remplacer les classes Filament si elles veulent personnaliser l'administration.

## Media Library et icones

Spatie Media Library sera utilise pour les images.

Collections envisagees :

- `default_marker_image` sur `GeoPointType` ;
- `marker_image` sur `GeoPoint` ;
- `layer_source` sur `MapLayer` pour les fichiers GeoJSON ;
- `map_preview` sur `Map`.

Le systeme d'icones doit rester ouvert.

Un champ `icon` peut stocker une cle d'icone, par exemple :

- `heroicon-o-map-pin`
- `lucide-camera`
- `custom-photo-marker`

Le rendu exact sera resolu par le plugin ou par l'application parente via un resolver configurable.



## Autorisations Filament

Le plugin s'aligne sur le plugin local `filament-permission-manager`.

Le systeme reste optionnel, et le plugin ne depend d'aucun gestionnaire de permissions : il **declare** ses droits,
comme tous les plugins `filament-*` (septembre 2026) :

- les actions de base des Resources (voir, creer, modifier, supprimer) passent par la policy du modele : avec
  `charlesstolive/filament-permission-manager`, sa policy de repli verifie `{cluster}.{resource}.{action}` ;
- les actions propres sont declarees dans `$specificPermissions` (`preview` pour une couche, `attach-media` pour un point
  et un type de point), avec leur libelle dans `$permissionLabels`, et verifiees par l'ability Gate
  `{classe de la Resource}.{action}` (`Support\MapPermissions`). Sans gestionnaire de permissions, tout reste ouvert a
  qui voit la liste ;
- le Cluster n'apparait que si une de ses listes est ouverte (regle native de Filament).

L'ancienne passerelle (`Support\PermissionManager`, `HasMapResourceAuthorization`, `HasMapClusterAuthorization`, config
`filament-map.authorization.*`) a ete retiree ; `import`, `export` et `pick-coordinates`, declares sans aucun ecran
correspondant, aussi.

Le format de permission reprend exactement la convention du permission-manager :

```text
{cluster}.{resource}.{action}
```

Avec le cluster par defaut, les permissions principales seront :

```text
cartographie.*
cartographie.map.*
cartographie.map.viewany
cartographie.map.view
cartographie.map.create
cartographie.map.edit
cartographie.map.delete
cartographie.maplayer.*
cartographie.geopoint.*
cartographie.geopointtype.*
```

Les wildcards sont gerees par le `PermissionService` du plugin d'autorisation.

Permissions specifiques prevues :

- `cartographie.map.preview`
- `cartographie.map.attach-point`
- `cartographie.map.detach-point`
- `cartographie.maplayer.import`
- `cartographie.maplayer.export`
- `cartographie.maplayer.preview`
- `cartographie.geopoint.pick-coordinates`
- `cartographie.geopoint.attach-media`
- `cartographie.geopointtype.attach-media`

Apres installation dans une application, il faudra enregistrer le plugin Filament Map dans le panel puis executer :

```bash
php artisan permissions:sync
```

La commande du permission-manager scanne les plugins Filament enregistres dans les panels et creera automatiquement les permissions des Resources exposees par `filament-map`.

## Settings

Les settings globaux peuvent etre portes par Spatie Settings.

Exemples :

- fournisseur de tuiles par defaut ;
- attribution OpenStreetMap ;
- styles GeoJSON par defaut ;
- style de marker par defaut ;
- comportement des popups ;
- zoom par defaut ;
- taille par defaut du composant ;
- activation des controles Leaflet.

La config publiee gere plutot la structure technique du package.

Les settings gerent les options modifiables depuis l'application.

## Orchestration externe

Le plugin doit rester utilisable sans orchestrateur, mais il doit etre facile a piloter depuis un modele externe.

Exemple futur : `Voyage`.

Le voyage pourra :

- choisir une carte ;
- choisir les points visibles ;
- changer l'ordre d'affichage ;
- activer/desactiver des couches ;
- modifier les popups ;
- ajouter photos, videos ou textes ;
- changer les options du layout ;
- piloter un scenario.

Le plugin ne doit pas essayer de modeliser tous ces contenus dans sa V1.

Il doit plutot exposer :

- des contrats ;
- des DTO ou payload builders ;
- des options JSON ;
- des hooks de transformation ;
- des evenements.

Contrats possibles :

```php
interface ProvidesFilamentMapPoints
{
    public function filamentMapPoints(): iterable;
}

interface ProvidesFilamentMapLayers
{
    public function filamentMapLayers(): iterable;
}

interface ProvidesFilamentMapOptions
{
    public function filamentMapOptions(): array;
}
```

Un orchestrateur pourra donc fournir une version enrichie des points sans modifier le coeur du plugin.

## Extension des points

Un point pourra etre etendu selon son type ou selon le contexte.

Exemples :

- une photo rattachee a un point ;
- une video ;
- un texte narratif ;
- une etape de parcours ;
- un lien vers une fiche metier ;
- une galerie ;
- une action Filament.

Le plugin doit separer :

- la position geographique ;
- l'apparence du marqueur ;
- le contenu affiche ;
- la logique metier externe.

En V1, le plugin peut proposer un contenu simple de popup/tooltip.

Les contenus riches seront fournis par l'application parente via overrides ou payload custom.

## Payload de rendu

Le rendu devrait passer par un service PHP qui transforme les modeles en payload stable.

Nom envisage : `MapPayloadBuilder`.

Responsabilites :

- charger la carte ;
- fusionner les settings globaux ;
- fusionner les options de la carte ;
- fusionner les overrides ;
- construire les couches ;
- construire les points ;
- produire un tableau serialisable.

Exemple simplifie :

```json
{
  "map": {
    "id": 1,
    "center": { "lat": 12.5657, "lng": 104.9910 },
    "zoom": 7,
    "options": {}
  },
  "layers": [],
  "points": [],
  "controls": {
    "layers": true,
    "zoom": true
  }
}
```

Cette frontiere est importante : Livewire ne doit pas assembler du JavaScript, et JavaScript ne doit pas deviner la structure metier.

## Priorites V1

1. Migrations et modeles principaux.
2. Config publiee du plugin.
3. Cluster Filament configurable.
4. Ressources Maps, Layers, GeoPoints, GeoPointTypes.
5. Media Library sur types, points et layers.
6. Composant Livewire de rendu.
7. JavaScript Leaflet structure en modules.
8. Couche GeoJSON stylisee.
9. Couche points.
10. Picker de coordonnees dans la ressource point.
11. Hooks simples pour overrides externes.

## Hors V1 probable

- Edition avancee de geometries.
- Dessin de polygones depuis l'interface.
- Snap sur ligne ou feature.
- Import massif GeoJSON avec mapping complexe.
- Timeline/scenario complet.
- Gestion native de galeries, videos et contenus narratifs riches.
- OpenLayers.

Ces sujets doivent rester possibles, mais ne doivent pas alourdir le socle initial.

## References techniques

Laravel expose des types spatiaux via les migrations, notamment `geometry` et `geography` avec subtype et SRID dans les versions recentes. La documentation Laravel indique aussi que le support depend du driver de base de donnees.

Reference : https://laravel.com/docs/11.x/migrations#spatial-types

La V1 devra confirmer le comportement exact avec Laravel 13, MySQL et MariaDB pendant l'implementation des migrations.
