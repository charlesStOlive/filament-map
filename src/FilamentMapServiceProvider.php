<?php

namespace CharlesStOlive\FilamentMap;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use CharlesStOlive\FilamentMap\Commands\InstallFilamentMapCommand;

class FilamentMapServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-map')
            ->hasConfigFile('filament-map')
            ->hasViews('filament-map')
            ->hasMigrations()
            ->hasCommands([
                InstallFilamentMapCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        //
    }
}
