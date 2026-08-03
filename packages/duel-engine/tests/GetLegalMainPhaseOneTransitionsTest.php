<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Duels\DuelStatus;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\getLegalMainPhaseOneTransitions;
use function DuelLegacy\DuelEngine\gxLegacyProfile;

final class GetLegalMainPhaseOneTransitionsTest extends TestCase
{
    public function test_gx_first_turn_only_allows_end_and_later_turns_allow_battle_first(): void
    {
        self::assertSame([DuelPhase::END], getLegalMainPhaseOneTransitions(TestFactory::activeDuel(DuelPhase::MAIN_1), gxLegacyProfile()));
        foreach ([2, 3, 12] as $turn) {
            self::assertSame([DuelPhase::BATTLE, DuelPhase::END], getLegalMainPhaseOneTransitions(TestFactory::activeDuel(DuelPhase::MAIN_1, $turn), gxLegacyProfile()));
        }
    }

    public function test_profile_can_allow_first_turn_battle_without_mutating_state(): void
    {
        $profile = TestFactory::profile(['id' => 'FIRST_TURN_BATTLE', 'battleOnFirstTurn' => true]);
        $duel = TestFactory::activeDuel(DuelPhase::MAIN_1)->with(['rulesProfileId' => $profile->id]);
        $snapshot = $duel->toArray();
        self::assertSame([DuelPhase::BATTLE, DuelPhase::END], getLegalMainPhaseOneTransitions($duel, $profile));
        self::assertSame($snapshot, $duel->toArray());
    }

    public function test_shared_main_phase_validation_rejects_invalid_state(): void
    {
        $duel = TestFactory::activeDuel(DuelPhase::MAIN_1);
        foreach ([
            [$duel->with(['status' => DuelStatus::PREPARING]), gxLegacyProfile(), 'Somente um Duelo ACTIVE pode processar a Fase Principal 1.'],
            [$duel->with(['phase' => DuelPhase::END]), gxLegacyProfile(), 'O Duelo deve estar na fase MAIN_1.'],
            [$duel->with(['turnNumber' => 1.5]), gxLegacyProfile(), 'turnNumber deve ser um inteiro maior ou igual a 1.'],
            [$duel->with(['currentPlayerId' => null]), gxLegacyProfile(), 'A Fase Principal 1 deve possuir jogador atual.'],
            [$duel, TestFactory::profile(['id' => 'OTHER']), 'RulesProfile incompatível com o Duelo.'],
        ] as [$state, $profile, $message]) {
            try {
                getLegalMainPhaseOneTransitions($state, $profile);
                self::fail();
            } catch (\Throwable $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }
}
