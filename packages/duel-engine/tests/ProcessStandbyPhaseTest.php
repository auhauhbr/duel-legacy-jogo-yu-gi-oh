<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Duels\DuelStatus;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\gxLegacyProfile;
use function DuelLegacy\DuelEngine\processStandbyPhase;

final class ProcessStandbyPhaseTest extends TestCase
{
    public function test_only_advances_to_main_one_with_defensive_copies(): void
    {
        $duel = TestFactory::activeDuel(DuelPhase::STANDBY, 2);
        $snapshot = $duel->toArray();
        $result = processStandbyPhase($duel, gxLegacyProfile());
        self::assertSame(DuelPhase::MAIN_1, $result->phase);
        self::assertSame($duel->turnNumber, $result->turnNumber);
        self::assertSame($duel->currentPlayerId, $result->currentPlayerId);
        self::assertEquals($duel->players, $result->players);
        self::assertNotSame($duel->players[0], $result->players[0]);
        self::assertEquals($duel->rngState, $result->rngState);
        self::assertSame($snapshot, $duel->toArray());
    }

    public function test_rejects_wrong_status_phase_and_current_player(): void
    {
        $duel = TestFactory::activeDuel(DuelPhase::STANDBY);
        foreach ([
            [$duel->with(['status' => DuelStatus::FINISHED]), 'Somente um Duelo ACTIVE pode processar a Fase de Apoio.'],
            [$duel->with(['phase' => DuelPhase::DRAW]), 'O Duelo deve estar na fase STANDBY.'],
            [$duel->with(['currentPlayerId' => null]), 'A Fase de Apoio deve possuir jogador atual.'],
            [$duel->with(['currentPlayerId' => 'unknown']), 'currentPlayerId é incompatível com turnOrder e com os jogadores.'],
        ] as [$state, $message]) {
            try {
                processStandbyPhase($state, gxLegacyProfile());
                self::fail();
            } catch (\Throwable $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }
}
