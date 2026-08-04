<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Duels\DuelResultReason;
use DuelLegacy\DuelEngine\Duels\DuelStatus;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\gxLegacyProfile;
use function DuelLegacy\DuelEngine\processDrawPhase;

final class ProcessDrawPhaseTest extends TestCase
{
    public function test_first_turn_skips_draw_and_later_turn_draws_top_card(): void
    {
        $first = TestFactory::activeDuel(DuelPhase::DRAW, 1);
        $skipped = processDrawPhase($first, gxLegacyProfile());
        self::assertSame($first->players[0]->cardZones->hand, $skipped->players[0]->cardZones->hand);
        self::assertSame(DuelPhase::STANDBY, $skipped->phase);
        self::assertEquals($first->rngState, $skipped->rngState);

        $second = TestFactory::activeDuel(DuelPhase::DRAW, 2);
        $current = $second->players[1];
        $drawn = processDrawPhase($second, gxLegacyProfile());
        self::assertSame([...TestFactory::ids($current->cardZones->hand), $current->cardZones->mainDeck->cards()[0]->id->value], TestFactory::ids($drawn->players[1]->cardZones->hand));
        self::assertSame(array_slice(TestFactory::ids($current->cardZones->mainDeck), 1), TestFactory::ids($drawn->players[1]->cardZones->mainDeck));
        self::assertSame(DuelPhase::STANDBY, $drawn->phase);
        self::assertSame($second->toArray(), TestFactory::activeDuel(DuelPhase::DRAW, 2)->toArray());
    }

    public function test_deck_out_finishes_without_changing_phase_or_rng(): void
    {
        $duel = TestFactory::activeDuel(DuelPhase::DRAW, 2);
        $players = $duel->players;
        $players[1] = TestFactory::withZoneIds($players[1], CardLocation::MAIN_DECK, []);
        $duel = $duel->with(['players' => $players]);
        $snapshot = $duel->toArray();
        $result = processDrawPhase($duel, gxLegacyProfile());
        self::assertSame(DuelStatus::FINISHED, $result->status);
        self::assertSame(DuelPhase::DRAW, $result->phase);
        self::assertSame('player-1', $result->winnerId);
        self::assertSame(DuelResultReason::DECK_OUT, $result->resultReason);
        self::assertEquals($duel->rngState, $result->rngState);
        self::assertNotSame($duel, $result);
        self::assertNotSame($duel->players[0], $result->players[0]);
        self::assertNotSame($duel->rngState, $result->rngState);
        self::assertSame($snapshot, $duel->toArray());
    }

    public function test_profile_can_enable_first_turn_draw(): void
    {
        $duel = TestFactory::activeDuel(DuelPhase::DRAW);
        $result = processDrawPhase($duel, TestFactory::profile(['drawOnFirstTurn' => true]));
        self::assertSame($duel->players[0]->cardZones->hand->count() + 1, $result->players[0]->cardZones->hand->count());
    }

    public function test_rejects_invalid_active_draw_state(): void
    {
        $duel = TestFactory::activeDuel(DuelPhase::DRAW);
        $cases = [
            [$duel->with(['status' => DuelStatus::PREPARING]), 'Somente um Duelo ACTIVE pode processar a Fase de Compra.'],
            [$duel->with(['phase' => DuelPhase::STANDBY]), 'O Duelo deve estar na fase DRAW.'],
            [$duel->with(['turnNumber' => 0]), 'turnNumber deve ser um inteiro maior ou igual a 1.'],
            [$duel->with(['currentPlayerId' => null]), 'A Fase de Compra deve possuir jogador atual.'],
            [$duel->with(['winnerId' => 'player-2']), 'Um Duelo ACTIVE não pode possuir vencedor.'],
            [$duel->with(['rngState' => null]), 'O Duelo deve possuir um estado de RNG.'],
        ];
        foreach ($cases as [$state, $message]) {
            try {
                processDrawPhase($state, gxLegacyProfile());
                self::fail();
            } catch (\Throwable $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }
}
