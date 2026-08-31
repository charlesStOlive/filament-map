<?php

namespace CharlesStOlive\FilamentMap\Tests\Unit;

use CharlesStOlive\FilamentMap\Enums\GeoPointActionTrigger;
use CharlesStOlive\FilamentMap\Enums\GeoPointActionType;
use CharlesStOlive\FilamentMap\Models\GeoPointAction;
use CharlesStOlive\FilamentMap\Services\MapPayloadBuilder;
use PHPUnit\Framework\TestCase;

class GeoPointActionContractTest extends TestCase
{
    public function test_action_values_are_cast_and_serialized_for_the_map_payload(): void
    {
        $action = new GeoPointAction([
            'key' => 'show-on-step',
            'trigger' => 'event',
            'trigger_event' => 'trip.step.activated',
            'type' => 'show',
            'payload' => ['step' => 12],
            'options' => ['once' => true],
            'is_active' => true,
        ]);

        self::assertSame(GeoPointActionTrigger::Event, $action->trigger);
        self::assertSame(GeoPointActionType::Show, $action->type);
        self::assertTrue($action->is_active);

        $builder = new class extends MapPayloadBuilder
        {
            public function serializeAction(GeoPointAction $action): array
            {
                return $this->action($action);
            }
        };

        self::assertSame([
            'id' => null,
            'key' => 'show-on-step',
            'name' => null,
            'trigger' => [
                'type' => 'event',
                'event' => 'trip.step.activated',
            ],
            'effect' => [
                'type' => 'show',
                'target' => null,
                'payload' => ['step' => 12],
            ],
            'options' => ['once' => true],
        ], $builder->serializeAction($action));
    }

    public function test_supported_triggers_and_effects_are_exposed_to_filament(): void
    {
        self::assertArrayHasKey('click', GeoPointActionTrigger::options());
        self::assertArrayHasKey('event', GeoPointActionTrigger::options());
        self::assertArrayHasKey('dispatch', GeoPointActionType::options());
        self::assertArrayHasKey('navigate', GeoPointActionType::options());
    }
}
