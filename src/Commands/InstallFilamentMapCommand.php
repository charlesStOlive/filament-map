<?php

namespace CharlesStOlive\FilamentMap\Commands;

use Illuminate\Console\Command;

class InstallFilamentMapCommand extends Command
{
    public $signature = 'filament-map:install';

    public $description = 'Install the FilamentMap plugin';

    public function handle(): int
    {
        $this->info('Installing FilamentMap...');

        $this->comment('Publishing configuration...');
        $this->callSilently('vendor:publish', [
            '--tag' => 'filament-map-config',
        ]);

        $this->info('FilamentMap installed successfully.');

        return self::SUCCESS;
    }
}
