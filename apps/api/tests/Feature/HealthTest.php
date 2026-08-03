<?php

declare(strict_types=1);

namespace Tests\Feature;

use DuelLegacy\DuelEngine\Engine;
use Tests\TestCase;

final class HealthTest extends TestCase
{
    public function test_health_endpoint_reports_only_that_the_api_is_active(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_laravel_application_loads_the_duel_engine_package(): void
    {
        self::assertTrue(class_exists(Engine::class));
        self::assertSame('0.0.0', Engine::VERSION);
    }
}
