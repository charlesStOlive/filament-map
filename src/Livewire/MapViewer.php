<?php

namespace CharlesStOlive\FilamentMap\Livewire;

use CharlesStOlive\FilamentMap\Models\MapScene;
use CharlesStOlive\FilamentMap\Services\MapPayloadBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class MapViewer extends Component
{
    public int|string|null $sceneId = null;

    public ?string $eventScope = null;

    public array $points = [];

    public array $layers = [];

    public array $options = [];

    public bool $replaceStoredPoints = false;

    public bool $replaceStoredLayers = false;

    public string $height;

    public string $width;

    public string $class = '';

    public bool $interactive = true;

    public bool $showControls = true;

    public bool $showRefresh = false;

    public bool $fitBounds = false;

    public int|string|null $selectedPointId = null;

    public function mount(
        ?string $height = null,
        ?string $width = null,
        string $class = '',
        ?string $eventScope = null,
        array $points = [],
        array $layers = [],
        array $options = [],
        bool $replaceStoredPoints = false,
        bool $replaceStoredLayers = false,
        bool $interactive = true,
        bool $showControls = true,
        bool $showRefresh = false,
        bool $fitBounds = false,
        MapScene|int|string|null $scene = null,
    ): void {
        $this->sceneId = $scene instanceof MapScene ? $scene->getKey() : $scene;
        $this->height = $height ?? config('filament-map.default.height', 'h-[500px]');
        $this->width = $width ?? config('filament-map.default.width', 'w-full');
        $this->class = $class;
        $this->eventScope = $eventScope ?? ($this->sceneId !== null ? 'scene-'.$this->sceneId : 'map-viewer-'.$this->getId());
        $this->points = $this->normalizePoints($points);
        $this->layers = array_values($layers);
        $this->options = $options;
        $this->replaceStoredPoints = $replaceStoredPoints || $points !== [];
        $this->replaceStoredLayers = $replaceStoredLayers || $layers !== [];
        $this->interactive = $interactive;
        $this->showControls = $showControls;
        $this->showRefresh = $showRefresh;
        $this->fitBounds = $fitBounds;
    }

    #[On('filament-map-points-replace')]
    public function replacePoints(array $points, ?string $scope = null): void
    {
        if (! $this->acceptsScope($scope)) {
            return;
        }

        $this->points = $this->normalizePoints($points);
        $this->replaceStoredPoints = true;
        $this->dispatchViewerUpdate();
    }

    #[On('filament-map-point-upsert')]
    public function upsertPoint(array $point, ?string $scope = null): void
    {
        if (! $this->acceptsScope($scope)) {
            return;
        }

        $point = $this->normalizePoint($point);

        if ($point === null) {
            return;
        }

        $this->seedPointOverrides();
        $index = collect($this->points)->search(
            fn (array $existing): bool => (string) ($existing['id'] ?? '') === (string) $point['id'],
        );

        if ($index === false) {
            $this->points[] = $point;
        } else {
            $this->points[$index] = $point;
        }

        $this->points = array_values($this->points);
        $this->dispatchViewerUpdate();
    }

    #[On('filament-map-point-remove')]
    public function removePoint(int|string $pointId, ?string $scope = null): void
    {
        if (! $this->acceptsScope($scope)) {
            return;
        }

        $this->seedPointOverrides();
        $this->points = array_values(array_filter(
            $this->points,
            fn (array $point): bool => (string) ($point['id'] ?? '') !== (string) $pointId,
        ));
        $this->dispatchViewerUpdate();
    }

    #[On('filament-map-points-clear')]
    public function clearPoints(?string $scope = null): void
    {
        if (! $this->acceptsScope($scope)) {
            return;
        }

        $this->points = [];
        $this->replaceStoredPoints = true;
        $this->selectedPointId = null;
        $this->dispatchViewerUpdate();
    }

    #[On('filament-map-point-select')]
    public function selectPoint(int|string|null $pointId = null, ?string $scope = null): void
    {
        if (! $this->acceptsScope($scope)) {
            return;
        }

        $this->selectedPointId = $pointId;
        $this->dispatchViewerUpdate();
    }

    #[On('filament-map-layers-replace')]
    public function replaceLayers(array $layers, ?string $scope = null): void
    {
        if (! $this->acceptsScope($scope)) {
            return;
        }

        $this->layers = array_values($layers);
        $this->replaceStoredLayers = true;
        $this->dispatchViewerUpdate();
    }

    #[On('filament-map-layers-refresh')]
    public function refreshLayers(?string $scope = null): void
    {
        if (! $this->acceptsScope($scope)) {
            return;
        }

        $this->replaceStoredLayers = false;
        $this->layers = [];
        $this->dispatchViewerUpdate();
    }

    #[On('filament-map-refresh')]
    public function refreshMap(?string $scope = null): void
    {
        if (! $this->acceptsScope($scope)) {
            return;
        }

        $this->dispatchViewerUpdate();
    }

    public function render(MapPayloadBuilder $payloadBuilder): View
    {
        $payload = $this->buildPayload($payloadBuilder);

        return view('filament-map::livewire.map-viewer', [
            'payload' => $payload,
            'mapDomId' => $this->mapDomId(),
        ]);
    }

    public function mapDomId(): string
    {
        return 'filament-map-'.$this->getId();
    }

    protected function overrides(): array
    {
        $overrides = [
            'map' => $this->options ?: null,
            'controls' => [
                'layers' => $this->showControls,
                'zoom' => $this->showControls,
            ],
            'state' => [
                'interactive' => $this->interactive,
                'fitBounds' => $this->fitBounds,
                'selectedPointId' => $this->selectedPointId,
                'eventScope' => $this->eventScope,
            ],
        ];

        if ($this->replaceStoredPoints) {
            $overrides['points'] = $this->points;
        }

        if ($this->replaceStoredLayers) {
            $overrides['layers'] = $this->layers;
        }

        return array_filter($overrides, fn ($value): bool => $value !== null);
    }

    protected function buildPayload(MapPayloadBuilder $payloadBuilder): ?array
    {
        $scene = $this->sceneId !== null ? MapScene::query()->find($this->sceneId) : null;

        return $scene ? $payloadBuilder->build($scene, $this->overrides()) : null;
    }

    protected function dispatchViewerUpdate(): void
    {
        $payload = $this->buildPayload(app(MapPayloadBuilder::class));

        if ($payload === null) {
            return;
        }

        $this->dispatch('filament-map:update', id: $this->mapDomId(), payload: $payload);
    }

    protected function acceptsScope(?string $scope): bool
    {
        return $scope === null || $scope === '' || $scope === $this->eventScope;
    }

    protected function seedPointOverrides(): void
    {
        if ($this->replaceStoredPoints) {
            return;
        }

        $payload = $this->buildPayload(app(MapPayloadBuilder::class));
        $this->points = $this->normalizePoints($payload['points'] ?? []);
        $this->replaceStoredPoints = true;
    }

    protected function normalizePoints(array $points): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $point): ?array => is_array($point) ? $this->normalizePoint($point) : null,
            $points,
        )));
    }

    protected function normalizePoint(array $point): ?array
    {
        $position = is_array($point['position'] ?? null)
            ? $point['position']
            : ['lat' => $point['lat'] ?? null, 'lng' => $point['lng'] ?? null];
        $lat = $position['lat'] ?? null;
        $lng = $position['lng'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $id = $point['id'] ?? null;

        if ($id === null || $id === '') {
            $id = 'external-'.sha1(json_encode([
                (float) $lat,
                (float) $lng,
                $point['name'] ?? null,
            ]));
        }

        unset($point['lat'], $point['lng']);

        return [
            ...$point,
            'id' => $id,
            'name' => (string) ($point['name'] ?? ''),
            'position' => [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
            ],
            'visible' => (bool) ($point['visible'] ?? true),
            'options' => is_array($point['options'] ?? null) ? $point['options'] : [],
        ];
    }
}
