<?php

return [

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
        'url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        'token' => env('FILAMENT_MAP_TOKEN', null),
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
        'maps' => 'filament_map_maps',
        'geo_point_types' => 'filament_map_geo_point_types',
        'geo_points' => 'filament_map_geo_points',
        'layers' => 'filament_map_layers',
        'map_layers' => 'filament_map_map_layer',
        'map_geo_point' => 'filament_map_geo_map_point',
    ],

    /*
    |--------------------------------------------------------------------------
    | Media collections
    |--------------------------------------------------------------------------
    */
    'media_collections' => [
        'map_preview' => 'map_preview',
        'layer_source' => 'layer_source',
        'default_marker_image' => 'default_marker_image',
        'marker_image' => 'marker_image',
    ],

    /*
    |--------------------------------------------------------------------------
    | Leaflet assets
    |--------------------------------------------------------------------------
    */
    'leaflet' => [
        'assets_path' => 'vendor/filament-map/filament-map.js',
        'css_url' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        'js_url' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    ],

];
