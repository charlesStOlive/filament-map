<?php

return [
    'tables' => [
        'maps' => 'filament_map_maps',
        'layers' => 'filament_map_layers',
        'geo_points' => 'filament_map_geo_points',
        'geo_point_types' => 'filament_map_geo_point_types',
        'map_layers' => 'filament_map_map_layer',
        'map_geo_point' => 'filament_map_geo_map_point',
    ],

    'cluster' => [
        'enabled' => true,
        'label' => 'Cartographie',
        'slug' => 'cartographie',
        'icon' => 'heroicon-o-map',
        'navigation_group' => null,
        'navigation_sort' => null,
    ],

    'resources' => [
        'maps' => true,
        'layers' => true,
        'geo_points' => true,
        'geo_point_types' => true,
    ],

    'authorization' => [
        'enabled' => true,
        'driver' => 'filament-permission-manager',
        'allow_without_permission_manager' => true,
        'permission_cluster' => null,
    ],

    'tiles' => [
        'provider' => 'openstreetmap',
        'url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        'token' => env('FILAMENT_MAP_TOKEN'),
    ],

    'default' => [
        'lat' => 48.8566,
        'lng' => 2.3522,
        'zoom' => 10,
        'min_zoom' => 2,
        'max_zoom' => 18,
        'height' => 'h-[500px]',
        'width' => 'w-full',
    ],

    'leaflet' => [
        'assets_path' => 'vendor/filament-map/filament-map.js',
        'css_url' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        'js_url' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    ],

    'media_collections' => [
        'map_preview' => 'map_preview',
        'layer_source' => 'layer_source',
        'marker_image' => 'marker_image',
        'default_marker_image' => 'default_marker_image',
    ],
];
