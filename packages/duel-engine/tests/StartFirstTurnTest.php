<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Duels\DuelStatus;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\gxLegacyProfile;
use function DuelLegacy\DuelEngine\startFirstTurn;

final class StartFirstTurnTest extends TestCase
{
    public function test_starts_first_turn_without_drawing_or_consuming_rng(): void
    {
        $prepared = TestFactory::preparedDuel('player-2');
        $snapshot = $prepared->toArray();
        $started = startFirstTurn($prepared, gxLegacyProfile());
        self::assertSame(DuelStatus::ACTIVE, $started->status);
        self::assertSame(1, $started->turnNumber);
        self::assertSame('player-2', $started->currentPlayerId);
        self::assertSame(DuelPhase::DRAW, $started->phase);
        self::assertSame($prepared->players[0]->cardZones->hand, $started->players[0]->cardZones->hand);
        self::assertEquals($prepared->rngState, $started->rngState);
        self::assertNotSame($prepared->rngState, $started->rngState);
        self::assertSame($snapshot, $prepared->toArray());
    }

    public function test_rejects_every_invalid_prepared_invariant(): void
    {
        $prepared = TestFactory::preparedDuel();
        $cases = [
            [$prepared->with(['status' => DuelStatus::ACTIVE]), gxLegacyProfile(), 'Somente um Duelo em PREPARING pode ser iniciado.'],
            [$prepared->with(['rngState' => null]), gxLegacyProfile(), 'O Duelo deve possuir um estado de RNG.'],
            [$prepared->with(['turnNumber' => 1]), gxLegacyProfile(), 'O Duelo preparado deve estar antes do primeiro turno.'],
            [$prepared->with(['currentPlayerId' => 'player-1']), gxLegacyProfile(), 'O Duelo preparado não pode possuir jogador atual.'],
            [$prepared->with(['phase' => DuelPhase::DRAW]), gxLegacyProfile(), 'O Duelo preparado não pode possuir fase atual.'],
            [$prepared->with(['winnerId' => 'player-2']), gxLegacyProfile(), 'O Duelo preparado não pode possuir vencedor.'],
            [$prepared, TestFactory::profile(['id' => 'OTHER']), 'RulesProfile incompatível com o Duelo.'],
        ];
        foreach ($cases as [$state, $profile, $message]) {
            try {
                startFirstTurn($state, $profile);
                self::fail();
            } catch (\Throwable $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }
}
