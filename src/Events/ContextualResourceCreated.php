<?php

namespace CharlesStOlive\FilamentMap\Events;

use Illuminate\Database\Eloquent\Model;

final class ContextualResourceCreated
{
    public function __construct(
        public readonly Model $record,
        public readonly string $context,
    ) {}
}
