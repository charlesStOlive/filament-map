<?php

namespace CharlesStOlive\FilamentMap;

use CharlesStOlive\FilamentMap\Commands\InstallFilamentMapCommand;
use CharlesStOlive\FilamentMap\Livewire\MapViewer;
use CharlesStOlive\FilamentMap\Services\Geocoding\Geocoder;
use CharlesStOlive\FilamentMap\Services\Geocoding\NominatimGeocoder;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentMapServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-map')
            ->hasConfigFile('filament-map')
            ->hasViews('filament-map')
            ->hasMigrations(['create_filament_map_tables', 'create_filament_map_scene_tables'])
            ->hasCommands([
                InstallFilamentMapCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(Geocoder::class, function ($app): Geocoder {
            $driver = config('filament-map.geocoding.driver', 'nominatim');

            return $app->make($driver === 'nominatim' ? NominatimGeocoder::class : $driver);
        });
    }

    public function packageBooted(): void
    {
        Livewire::component('filament-map-viewer', MapViewer::class);

        $this->publishes([
            __DIR__.'/../resources/js' => public_path('vendor/filament-map'),
        ], 'filament-map-assets');

        $this->publishes([
            __DIR__.'/../docs/knowledge-base' => base_path('docs/knowledge-base/fr'),
        ], 'filament-map-docs');
    }
}
