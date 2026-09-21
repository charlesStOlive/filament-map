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
ouvre la carte dans un grand popup (7xl, la carte prend les deux tiers de la
hauteur de l'écran ; un bouton la passe en **plein écran**, Échap en sort).
Cliquer sur la carte ou déplacer le repère met le formulaire à jour en direct ;
« Terminé » referme le popup.

Au-dessus de la carte, un champ **cherche un lieu ou une adresse** (« Siem Reap »,
« 12 rue de la Paix, Paris ») : les résultats se listent, un clic cale la carte
dessus et pose le repère. Voir « Recherche d'adresse » plus bas.

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
carte), `->viewport('zoom')` (cadrage d'une vue de départ : la position est
celle du **repère**, posé au clic ou déplacé, et le zoom est celui que la carte
affiche, arrondi au centième ; déplacer la carte ne change pas la position, le
bouton « Centrer sur le repère » y ramène la vue ; le champ de zoom reste un
champ frère), `->initialZoom()`, `->mapHeight()`,
`->withoutMap()` (saisie seule), `->live(onBlur: true)`. `MapViewportPicker`
prend `->withoutSearch()` pour retirer la recherche d'adresse.

Il remplace l'ancien `CoordinatePicker` (dont le bouton n'était écouté par
personne). `MapViewportPicker` reste disponible seul, et porte toujours
`captureZoomAction()` pour les champs de zoom.

### Clés des fournisseurs de fonds de carte

Une couche cite sa clé sans la porter : `{key:maptiler}` dans son URL (source ou
`options.style_url`) est remplacé, à l'affichage, par `config('filament-map.keys.maptiler')`
(variable `MAPTILER_API_KEY`). La clé vit dans le `.env`, pas en base, et un
changement de clé ne demande pas de retoucher les couches. Pour un autre
fournisseur, ajouter une entrée à `keys` suffit (`{key:autre}`).

### Recherche d'adresse

`MapViewportPicker` (donc `CoordinatesInput`) interroge un **service de
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
