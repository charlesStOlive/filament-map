<?php

namespace CharlesStOlive\FilamentMap;

use CharlesStOlive\FilamentMap\Models\Map;
use CharlesStOlive\FilamentMap\Services\MapPayloadBuilder;

class FilamentMap
{
    public function payload(Map $map, array $overrides = []): array
    {
        return app(MapPayloadBuilder::class)->build($map, $overrides);
    }
}
