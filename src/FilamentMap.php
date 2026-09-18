<?php

namespace CharlesStOlive\FilamentMap;

use CharlesStOlive\FilamentMap\Models\MapScene;
use CharlesStOlive\FilamentMap\Services\MapPayloadBuilder;

class FilamentMap
{
    public function payload(MapScene $scene, array $overrides = []): ?array
    {
        return app(MapPayloadBuilder::class)->build($scene, $overrides);
    }
}
