<?php

namespace CharlesStOlive\FilamentMap\Services\Geocoding;

/**
 * Un lieu trouvé : son nom, son point, et — quand le service le donne — le cadre qui l'englobe (une ville, un pays),
 * pour que la carte s'y cale à la bonne échelle plutôt qu'à un zoom fixe.
 */
final readonly class GeocodingResult
{
    /**
     * @param  array{south: float, west: float, north: float, east: float}|null  $bounds
     */
    public function __construct(
        public string $label,
        public float $latitude,
        public float $longitude,
        public ?array $bounds = null,
    ) {}

    /** @return array{label: string, lat: float, lng: float, bounds: array{south: float, west: float, north: float, east: float}|null} */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'bounds' => $this->bounds,
        ];
    }
}
