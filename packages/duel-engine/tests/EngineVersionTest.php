<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Engine;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\duelEngineVersion;

final class EngineVersionTest extends TestCase
{
    public function test_exposes_version(): void
    {
        self::assertSame('0.0.0', Engine::VERSION);
        self::assertSame(Engine::VERSION, duelEngineVersion());
    }
}
