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
    | Leaflet assets
    |--------------------------------------------------------------------------
    */
    'leaflet' => [
        'assets_path' => 'vendor/filament-map/filament-map.js',
        'css_url' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        'js_url' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    ],

];
