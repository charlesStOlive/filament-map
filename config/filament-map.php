<?php

use CharlesStOlive\FilamentMap\Filament\Clusters\MapCluster;

return [

    'cluster' => [
        'enabled' => true,
        'class' => MapCluster::class,
        'label' => 'Cartographie',
        'slug' => 'cartographie',
        'icon' => 'heroicon-o-map',
        'navigation_group' => null,
        'navigation_sort' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default tile provider
    |--------------------------------------------------------------------------
    |
    | The default tile provider to use for maps.
    | Supported: "openstreetmap", "mapbox", "google"
    |
    */
    'tiles' => [
        'provider' => 'openstreetmap',
        // MapLibre GL ne sait pas substituer {s} (spécifique à Leaflet) : un
        // seul sous-domaine, pas de rotation.
        'url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        'token' => env('FILAMENT_MAP_TOKEN', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Clés des fournisseurs de fonds de carte
    |--------------------------------------------------------------------------
    |
    | Une couche peut citer une clé sans l'écrire en base : « {key:maptiler} »
    | dans son URL (source ou `style_url`) est remplacé, à l'affichage, par la
    | valeur ci-dessous. La clé vit dans le .env, et un changement de clé ne
    | demande pas de retoucher les couches.
    |
    */
    'keys' => [
        'maptiler' => env('MAPTILER_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recherche d'adresse
    |--------------------------------------------------------------------------
    |
    | Le sélecteur de carte propose de chercher un lieu par son nom ou son
    | adresse. Le pilote par défaut est Nominatim (OpenStreetMap) : gratuit et
    | sans clé, mais limité à une requête par seconde et à un usage occasionnel
    | (https://operations.osmfoundation.org/policies/nominatim/). Pour un autre
    | service, `driver` peut être le nom d'une classe qui implémente
    | CharlesStOlive\FilamentMap\Services\Geocoding\Geocoder.
    |
    */
    'geocoding' => [
        'enabled' => true,
        'driver' => 'nominatim',
        'url' => env('FILAMENT_MAP_GEOCODING_URL', 'https://nominatim.openstreetmap.org/search'),
        // Identifie l'application auprès du service (exigé par Nominatim). Par défaut : « nom de l'app (URL) ».
        'user_agent' => env('FILAMENT_MAP_GEOCODING_USER_AGENT'),
        // Un courriel de contact, que Nominatim préfère à un User-Agent seul en cas d'abus.
        'email' => env('FILAMENT_MAP_GEOCODING_EMAIL'),
        // Langues des noms de lieux, par ordre de préférence (« fr,en »). Par défaut : celle de l'application, puis l'anglais.
        'language' => null,
        // Limiter la recherche à des pays (codes ISO séparés par des virgules, « fr,kh »), ou null pour le monde entier.
        'country_codes' => env('FILAMENT_MAP_GEOCODING_COUNTRIES'),
        'timeout' => 6,
        // Durée de garde d'une réponse (30 jours) ; 0 pour ne rien garder.
        'cache_seconds' => 60 * 60 * 24 * 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default map options
    |--------------------------------------------------------------------------
    */
    'default' => [
        'lat' => 48.8566,
        'lng' => 2.3522,
        'zoom' => 10,
        'min_zoom' => 2,
        'max_zoom' => 18,
        'height' => 'h-[500px]',
        'width' => 'w-full',
    ],

    /*
    |--------------------------------------------------------------------------
    | Point rendering and clustering
    |--------------------------------------------------------------------------
    */
    'markers' => [
        'shape' => 'pin',
        'content_type' => 'icon',
    ],

    'clustering' => [
        'enabled' => false,
        'max_zoom' => 14,
        'radius' => 80,
        'group_by_type' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'scenes' => 'filament_map_scenes',
        'scene_layers' => 'filament_map_scene_layer',
        'geo_point_types' => 'filament_map_geo_point_types',
        'geo_points' => 'filament_map_geo_points',
        'layers' => 'filament_map_layers',
    ],

    /*
    |--------------------------------------------------------------------------
    | Media collections
    |--------------------------------------------------------------------------
    */
    'media_collections' => [
        'layer_source' => 'layer_source',
        'default_marker_image' => 'default_marker_image',
        'marker_image' => 'marker_image',
    ],

    /*
    |--------------------------------------------------------------------------
    | MapLibre GL assets
    |--------------------------------------------------------------------------
    */
    'maplibre' => [
        'assets_path' => 'vendor/filament-map/filament-map.js',
        'css_url' => 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css',
        'js_url' => 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js',
    ],

];
