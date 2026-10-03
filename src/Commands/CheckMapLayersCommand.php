<?php

namespace CharlesStOlive\FilamentMap\Commands;

use CharlesStOlive\FilamentMap\Models\MapLayer;
use CharlesStOlive\FilamentMap\Services\MapLayerChecker;
use CharlesStOlive\FilamentMap\Support\MapLayerCheck;
use Illuminate\Console\Command;

/** Vérifie les couches et garde le résultat sur chacune : à lancer après un changement de clé, ou à planifier. */
class CheckMapLayersCommand extends Command
{
    public $signature = 'filament-map:check-layers {keys?* : Clés des couches à vérifier (toutes par défaut)}';

    public $description = 'Vérifie que les couches cartographiques s’affichent (source joignable, contenu attendu)';

    public function handle(MapLayerChecker $checker): int
    {
        $layers = MapLayer::query()
            ->when($this->argument('keys'), fn ($query, array $keys) => $query->whereIn('key', $keys))
            ->orderBy('name')
            ->get();

        $rows = $layers->map(function (MapLayer $layer) use ($checker): array {
            $check = $checker->checkAndStore($layer);

            return [$layer->key, $layer->renderType(), MapLayerCheck::label($check->status), $check->message];
        });

        $this->table(['Clé', 'Type', 'État', 'Raison'], $rows->all());

        return $layers->contains('check_status', MapLayer::CHECK_ERROR) ? self::FAILURE : self::SUCCESS;
    }
}
