<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\gxLegacyProfile;
use function DuelLegacy\DuelEngine\transitionFromMainPhaseOne;

final class TransitionFromMainPhaseOneTest extends TestCase
{
    /** @return iterable<array{int, DuelPhase}> */
    public static function legalTransitions(): iterable
    {
        yield [1, DuelPhase::END];
        yield [2, DuelPhase::BATTLE];
        yield [2, DuelPhase::END];
    }

    #[DataProvider('legalTransitions')]
    public function test_changes_only_phase_and_returns_independent_state(int $turn, DuelPhase $target): void
    {
        $duel = TestFactory::activeDuel(DuelPhase::MAIN_1, $turn);
        $snapshot = $duel->toArray();
        $result = transitionFromMainPhaseOne($duel, gxLegacyProfile(), $target);
        self::assertSame($target, $result->phase);
        self::assertSame($snapshot, $duel->toArray());
        self::assertEquals($duel->players, $result->players);
        self::assertNotSame($duel->players[0], $result->players[0]);
        self::assertEquals($duel->rngState, $result->rngState);
        self::assertNotSame($duel->rngState, $result->rngState);
    }

    public function test_rejects_battle_on_first_gx_turn_and_other_targets(): void
    {
        foreach ([DuelPhase::BATTLE, DuelPhase::DRAW, DuelPhase::STANDBY, DuelPhase::MAIN_1, DuelPhase::MAIN_2] as $target) {
            try {
                transitionFromMainPhaseOne(TestFactory::activeDuel(DuelPhase::MAIN_1), gxLegacyProfile(), $target);
                self::fail();
            } catch (\Throwable $exception) {
                self::assertSame("Transição MAIN_1 → {$target->value} não permitida.", $exception->getMessage());
            }
        }
    }
}
