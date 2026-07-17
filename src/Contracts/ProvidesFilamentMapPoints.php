<?php

namespace CharlesStOlive\FilamentMap\Contracts;

interface ProvidesFilamentMapPoints
{
    public function filamentMapPoints(): iterable;
}
