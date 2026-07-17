<?php

namespace CharlesStOlive\FilamentMap\Contracts;

interface ProvidesFilamentMapLayers
{
    public function filamentMapLayers(): iterable;
}
