# filament-map

A Filament plugin to integrate interactive maps into your Filament admin panel.

## Installation

You can install the package via composer:

```bash
composer require charlesstolive/filament-map
```

Publish the config file and run the install command:

```bash
php artisan filament-map:install
```

## Usage

Register the plugin in your Filament panel provider:

```php
use CharlesStOlive\FilamentMap\FilamentMapPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentMapPlugin::make(),
        ]);
}
```

## Livewire map viewer

The same component can render a saved map in a Filament schema or in any
Livewire front-end view:

```blade
<livewire:filament-map-viewer :map="$map" event-scope="trip-map" :fit-bounds="true" />
```

See [the Livewire viewer documentation](docs/livewire-map-viewer.md) for the
Filament integration and the event contract used by external point components.

## Configuration

See `config/filament-map.php` for available options.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
