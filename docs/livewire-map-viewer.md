# Visualiseur de carte Livewire

`filament-map-viewer` est le composant de rendu commun au back-office
Filament et au front Livewire. Il charge la carte enregistrée, ses layers actifs
et ses points enregistrés, puis délègue leur rendu à Leaflet.

## Utilisation dans une vue Livewire

```blade
<livewire:filament-map-viewer
    :map="$map"
    event-scope="programme-map"
    height="h-[600px]"
    :show-controls="true"
    :fit-bounds="true"
/>
```

`map` accepte un modèle `Map` ou son identifiant. `event-scope` identifie
l'instance qui doit recevoir les événements. Il est conseillé de toujours le
définir lorsqu'une page peut contenir plusieurs cartes.

Options publiques principales :

- `height` et `width` : classes de taille du conteneur ;
- `interactive` : autorise les événements de clic sur la carte ;
- `showControls` : affiche le zoom et le sélecteur de layers Leaflet ;
- `fitBounds` : recadre sur le contenu visible ;
- `showRefresh` : affiche un bouton de rechargement des données enregistrées.

## Utilisation dans un schéma Filament

```php
use CharlesStOlive\FilamentMap\Livewire\MapViewer;
use Filament\Schemas\Components\Livewire;

Livewire::make(MapViewer::class, fn ($record): array => [
    'map' => $record,
    'eventScope' => 'map-' . $record->getKey(),
    'height' => 'h-[520px]',
    'showRefresh' => true,
])
    ->key(fn ($record): string => 'map-viewer-' . $record->getKey());
```

La ressource `MapResource` contient déjà cet aperçu pour les cartes
enregistrées. Les modifications du formulaire parent doivent être enregistrées
avant d'être rechargées dans le composant enfant.

## Contrat des géopoints externes

Un autre composant Livewire peut piloter les marqueurs sans dépendre de Leaflet.
Chaque événement accepte un `scope` optionnel. Sans scope, toutes les cartes
présentes sur la page reçoivent l'événement.

### Remplacer tous les points

```php
$this->dispatch(
    'filament-map-points-replace',
    scope: 'programme-map',
    points: $points,
);
```

### Ajouter ou mettre à jour un point

```php
$this->dispatch(
    'filament-map-point-upsert',
    scope: 'programme-map',
    point: [
        'id' => $place->getKey(),
        'name' => $place->name,
        'lat' => $place->latitude,
        'lng' => $place->longitude,
        'tooltip' => $place->name,
        'popup' => $place->description,
    ],
);
```

Le composant accepte également `position: ['lat' => ..., 'lng' => ...]`.

### Retirer, vider ou sélectionner

```php
$this->dispatch(
    'filament-map-point-remove',
    scope: 'programme-map',
    pointId: $placeId,
);

$this->dispatch('filament-map-points-clear', scope: 'programme-map');

$this->dispatch(
    'filament-map-point-select',
    scope: 'programme-map',
    pointId: $placeId,
);
```

La sélection recentre la carte sur le marqueur et ouvre sa popup ou son tooltip.

### Format complet d'un point

```php
[
    'id' => 42,
    'name' => 'Phnom Penh',
    'position' => ['lat' => 11.5564, 'lng' => 104.9282],
    'visible' => true,
    'tooltip' => 'Phnom Penh',
    'popup' => '<strong>Capitale</strong>',
    'icon' => null,
    'color' => '#2563eb',
    'image' => null,
    'style' => [],
    'options' => [
        'marker' => [],
    ],
    'layerId' => null,
    'sortOrder' => 0,
]
```

Seules les coordonnées sont obligatoires. Un identifiant stable est fortement
recommandé pour permettre les mises à jour, suppressions et sélections.

## Événements émis par la carte

Un clic sur un marqueur émet :

- l'événement navigateur `filament-map:point-clicked` ;
- l'événement Livewire `filament-map-point-clicked`.

Un clic sur la carte émet :

- l'événement navigateur `filament-map:coordinates-picked` ;
- l'événement Livewire `filament-map-coordinates-picked`.

Les événements contiennent `mapId`, `scope` et respectivement `point` ou
`lat` / `lng`. Un futur composant de gestion des géopoints peut donc écouter
ces événements :

```php
use Livewire\Attributes\On;

#[On('filament-map-point-clicked')]
public function pointClicked(
    array $point,
    int|string|null $mapId = null,
    ?string $scope = null,
): void
{
    // Mettre à jour la sélection métier.
}
```

## Actualisation des layers

Les layers enregistrés sont chargés automatiquement depuis la relation de la
carte. Après une modification faite par un autre composant :

```php
$this->dispatch('filament-map-layers-refresh', scope: 'programme-map');
```

Pour un aperçu non enregistré, une liste de payloads déjà construits peut être
envoyée avec `filament-map-layers-replace` et le paramètre `layers`.
