<?php

namespace CharlesStOlive\FilamentMap\Livewire;

use CharlesStOlive\FilamentMap\Models\Map;
use CharlesStOlive\FilamentMap\Services\MapPayloadBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MapViewer extends Component
{
    public int|string|null $mapId = null;

    public array $points = [];

    public array $layers = [];

    public array $options = [];

    public string $height;

    public string $width;

    public string $class = '';

    public bool $interactive = true;

    public bool $showControls = true;

    public bool $fitBounds = false;

    public int|string|null $selectedPointId = null;

    public function mount(Map|int|string|null $map = null, ?string $height = null, ?string $width = null, string $class = ''): void
    {
        $this->mapId = $map instanceof Map ? $map->getKey() : $map;
        $this->height = $height ?? config('filament-map.default.height', 'h-[500px]');
        $this->width = $width ?? config('filament-map.default.width', 'w-full');
        $this->class = $class;
    }

    public function coordinatesPicked(float $lat, float $lng): void
    {
        $this->dispatch('filament-map-coordinates-picked', lat: $lat, lng: $lng);
    }

    public function render(MapPayloadBuilder $payloadBuilder): View
    {
        $map = $this->mapId !== null ? Map::query()->find($this->mapId) : null;

        return view('filament-map::livewire.map-viewer', [
            'payload' => $map ? $payloadBuilder->build($map, $this->overrides()) : null,
        ]);
    }

    protected function overrides(): array
    {
        return array_filter([
            'points' => $this->points ?: null,
            'layers' => $this->layers ?: null,
            'map' => $this->options ?: null,
            'controls' => [
                'layers' => $this->showControls,
                'zoom' => $this->showControls,
            ],
            'state' => [
                'interactive' => $this->interactive,
                'fitBounds' => $this->fitBounds,
                'selectedPointId' => $this->selectedPointId,
            ],
        ], fn ($value): bool => $value !== null);
    }
}
