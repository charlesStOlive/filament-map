<?php

namespace CharlesStOlive\FilamentMap\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \CharlesStOlive\FilamentMap\FilamentMap
 */
class FilamentMap extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CharlesStOlive\FilamentMap\FilamentMap::class;
    }
}
