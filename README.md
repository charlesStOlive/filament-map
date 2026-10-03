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

## Apparence des points

`MapPayloadBuilder::appearance()` (appelé par `point()`) prépare `appearance`, que
`resources/js/layers/marker-element.js` dessine — sur la carte comme dans l'aperçu d'un type. Toute forme est un SVG :
celles fournies (`Support\MarkerShapes::BUILT_IN` : épingle, cercle, étoile) comme un SVG personnalisé, nettoyé et lu par
`Support\MarkerSvg`. Le SVG désigne sa **zone de contenu** par un `circle`, une `ellipse` ou un `rect` marqué
`data-slot` (traduite en % : `slot`), et son **ancrage** par `data-anchor` sur sa racine. L'image, l'icône (déjà rendue
en SVG côté serveur) ou le texte se posent dans la zone ; sans zone, la forme reste seule. Rien n'y lève d'erreur : ce
qui manque se rabat sur l'icône, puis sur la forme seule ; un SVG illisible, sur l'épingle.

Un consommateur (un parcours, une application) peut :

- **proposer une image** : `point($point, image: $url)`. Elle passe après l'image propre au point et avant celle du
  type, et n'est montrée que si le type l'accepte (`GeoPointType::acceptsImage()` : contenu « Image » et forme avec
  une zone de contenu) ; sinon elle est ignorée ;
- **donner sa couleur** au point sans créer de type : l'option `color` du point (`options.color`) l'emporte sur celle du
  type ;
- passer des options MapLibre par `options.marker` (`className`, `anchor`, `offset`…) ; `scale` y agrandit le dessin.

Un point sans `appearance` garde le marqueur par défaut de MapLibre.

## Saisir une position : `MapPositionInput`

Un composant de formulaire pour une **position sur la carte** et les réglages qui
l'accompagnent (zoom, zoom minimum et maximum, et d'autres à volonté).

- **Dans la page**, un **résumé mis en forme** — latitude, longitude, zoom… — avec
  un aperçu carré de la carte et une icône pour le modifier.
- **Dans un grand popup**, le formulaire : la position se choisit en cliquant sur la
  carte ou en déplaçant le repère, en saisissant ses propres coordonnées, ou en
  **cherchant une adresse** ; le zoom, le zoom minimum et le zoom maximum se
  prennent sur la carte avec leur bouton (le zoom de départ suit aussi la carte
  quand on la zoome). Un bouton passe la carte en **plein écran**, un autre la
  recentre sur le repère.
- **Rien n'est reporté dans le formulaire avant « Valider »** : la croix, Échap et
  « Annuler » abandonnent les changements, et le popup se rouvre sur les valeurs
  enregistrées. « Valider » vérifie la cohérence (zoom minimum ≤ zoom de départ ≤ zoom
  maximum, latitude et longitude ensemble).

Les valeurs restent dans des **champs frères** du composant (colonnes SQL, ou clés
d'un tableau JSON), avec leurs règles de validation :

```php
MapPositionInput::make()
    ->label('Vue de départ')
    ->latitudeField('map_center_latitude')->longitudeField('map_center_longitude')
    ->zoomField('map_zoom', 'Zoom de départ')
    ->minZoomField('map_min_zoom', 'Zoom min')
    ->maxZoomField('map_max_zoom', 'Zoom max')
    ->thumbnailField('map_thumbnail')
    ->scene(fn (Get $get) => MapScene::find($get('map_scene_id')));
```

Options : `->label()` (« Position » par défaut), `->required()` (position
obligatoire), `->zoomField()`, `->minZoomField()`, `->maxZoomField()`, `->field($nom,
$libellé, min:, max:, step:, kind:, follow:, role:)` pour d'autres réglages
numériques (`kind: 'zoom'` leur donne le bouton « zoom actuel »),
`->thumbnailField()`, `->scene($scene)` (couches de la carte ; sans scène connue, ni
carte ni bouton de recherche : on ne saisit que les coordonnées), `->initialZoom()`,
`->mapHeight()`, `->withoutSearch()`. Les coordonnées sont gardées à 5 décimales
(environ un mètre), les zooms au centième.

**L'aperçu carré** est une image de la carte, centrée sur le repère, prise **dans le
navigateur** au moment de « Valider » : le canevas WebGL de MapLibre est lu
directement (`canvas.toDataURL`, recadré et réduit à 192 × 192 px, environ 8 à
12 Ko), sans Browsershot ni navigateur côté serveur. Il est gardé en texte
(`data:image/jpeg;base64,…`) dans le champ `->thumbnailField()`. Il faut que le fond de
carte autorise le chargement de ses tuiles depuis un autre domaine (CORS), ce que font
OpenStreetMap et MapTiler ; sinon aucun aperçu n'est produit (la position, elle, reste
valide).

Le JavaScript est `resources/js/map-position-input.js` (copié dans
`public/vendor/filament-map`) ; la recherche d'adresse est `MapPositionEditor::searchAddress()`.

`CoordinatesInput` et `MapViewportPicker` (ancien sélecteur : les valeurs changeaient en
direct, le repère suivait la carte) restent disponibles mais sont **obsolètes** :
utiliser `MapPositionInput`.

### Clés des fournisseurs de fonds de carte

Une couche cite sa clé sans la porter : `{key:maptiler}` dans son URL (source ou
`options.style_url`) est remplacé, à l'affichage, par `config('filament-map.keys.maptiler')`
(variable `MAPTILER_API_KEY`). La clé vit dans le `.env`, pas en base, et un
changement de clé ne demande pas de retoucher les couches. Pour un autre
fournisseur, ajouter une entrée à `keys` suffit (`{key:autre}`).

### Recherche d'adresse

`MapPositionEditor` (donc `MapPositionInput`) interroge un **service de
géocodage** par le serveur : la méthode `searchAddress()` du champ, appelée depuis
la carte avec `$wire.callSchemaComponentMethod()` (aucune route à déclarer, les
droits sont ceux de la page). Le pilote par défaut est **Nominatim**
(OpenStreetMap) : gratuit, sans clé, mais fait pour un usage occasionnel — une
requête par seconde au plus, un User-Agent qui identifie l'application, pas de
recherche à chaque frappe (la recherche se lance au bouton ou à Entrée), et les
réponses gardées. `NominatimGeocoder` le respecte : limite d'une requête par
seconde pour toute l'application, User-Agent « nom de l'app (URL) », réponses en
cache 30 jours. Conditions : <https://operations.osmfoundation.org/policies/nominatim/>.

Les réglages sont sous la clé `geocoding` de `config/filament-map.php` (variables
`FILAMENT_MAP_GEOCODING_URL`, `_USER_AGENT`, `_EMAIL`, `_COUNTRIES`) :
`enabled`, `driver`, `url` (pour une instance auto-hébergée), `email`,
`language` (par défaut la langue de l'application, puis l'anglais : sans nom dans
la première, Nominatim retombe sur l'écriture locale), `country_codes`, `timeout`
et `cache_seconds`.

Pour un autre service (Photon, MapTiler, Geoapify, l'API adresse du
gouvernement…), on écrit une classe qui implémente
`Services\Geocoding\Geocoder` — `search(string $query, int $limit): array` de
`GeocodingResult`, `GeocodingException` pour un message à l'utilisateur — et on
la nomme dans `geocoding.driver`.

Le JavaScript du sélecteur est copié dans `public/vendor/filament-map` :
après une mise à jour du paquet,
`php artisan vendor:publish --tag=filament-map-assets --force`.

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
