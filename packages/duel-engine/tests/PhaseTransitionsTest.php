<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Phases\BattleStep;
use DuelLegacy\DuelEngine\Phases\DamageStepWindow;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Phases\PhaseOrder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\getNextStandardPhase;
use function DuelLegacy\DuelEngine\isValidStandardPhaseTransition;

final class PhaseTransitionsTest extends TestCase
{
    public function test_orders_match_type_script_contract(): void
    {
        self::assertSame(DuelPhase::cases(), PhaseOrder::standard());
        self::assertSame(BattleStep::cases(), PhaseOrder::battleSteps());
        self::assertSame(DamageStepWindow::cases(), PhaseOrder::damageStepWindows());
    }

    /** @return iterable<array{DuelPhase, ?DuelPhase}> */
    public static function nextPhases(): iterable
    {
        yield [DuelPhase::DRAW, DuelPhase::STANDBY];
        yield [DuelPhase::STANDBY, DuelPhase::MAIN_1];
        yield [DuelPhase::MAIN_1, DuelPhase::BATTLE];
        yield [DuelPhase::BATTLE, DuelPhase::MAIN_2];
        yield [DuelPhase::MAIN_2, DuelPhase::END];
        yield [DuelPhase::END, null];
    }

    #[DataProvider('nextPhases')]
    public function test_gets_next_phase(DuelPhase $current, ?DuelPhase $next): void
    {
        self::assertSame($next, getNextStandardPhase($current));
    }

    public function test_validates_standard_and_optional_transitions(): void
    {
        self::assertTrue(isValidStandardPhaseTransition(DuelPhase::MAIN_1, DuelPhase::BATTLE));
        self::assertTrue(isValidStandardPhaseTransition(DuelPhase::MAIN_1, DuelPhase::END));
        self::assertTrue(isValidStandardPhaseTransition(DuelPhase::BATTLE, DuelPhase::END));
        self::assertFalse(isValidStandardPhaseTransition(DuelPhase::DRAW, DuelPhase::MAIN_1));
        foreach (DuelPhase::cases() as $phase) {
            self::assertFalse(isValidStandardPhaseTransition($phase, $phase));
            self::assertFalse(isValidStandardPhaseTransition(DuelPhase::END, $phase));
        }
    }
}
