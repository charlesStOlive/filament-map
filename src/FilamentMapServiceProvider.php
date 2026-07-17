<?php

namespace CharlesStOlive\FilamentMap;

use CharlesStOlive\FilamentMap\Livewire\MapViewer;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
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
            ->hasMigrations([
                'create_filament_map_tables',
            ])
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->publishAssets();
            });
    }

    public function packageBooted(): void
    {
        Livewire::component('filament-map-viewer', MapViewer::class);

        Blade::componentNamespace('CharlesStOlive\\FilamentMap\\View\\Components', 'filament-map');

        $this->publishes([
            __DIR__ . '/../resources/js' => public_path('vendor/filament-map'),
        ], 'filament-map-assets');
    }
}
