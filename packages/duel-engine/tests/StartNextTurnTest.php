<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Duels\DuelResultReason;
use DuelLegacy\DuelEngine\Duels\DuelStatus;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\gxLegacyProfile;
use function DuelLegacy\DuelEngine\startNextTurn;

final class StartNextTurnTest extends TestCase
{
    /** @return iterable<array{int, int, string}> */
    public static function turns(): iterable
    {
        yield [1, 2, 'player-2'];
        yield [2, 3, 'player-1'];
        yield [7, 8, 'player-2'];
        yield [8, 9, 'player-1'];
    }

    #[DataProvider('turns')]
    public function test_alternates_turn_order_and_resets_only_starting_player(int $turn, int $nextTurn, string $nextPlayer): void
    {
        $duel = TestFactory::endDuel($turn);
        $players = array_map(static fn ($player) => $player->with(['normalSummonsUsed' => $player->playerId === 'player-1' ? 3 : 4]), $duel->players);
        $duel = $duel->with(['players' => $players]);
        $snapshot = $duel->toArray();
        $result = startNextTurn($duel, gxLegacyProfile());
        self::assertSame($nextTurn, $result->turnNumber);
        self::assertSame($nextPlayer, $result->currentPlayerId);
        self::assertSame(DuelPhase::DRAW, $result->phase);
        foreach ($result->players as $player) {
            self::assertSame($player->playerId === $nextPlayer ? 0 : ($player->playerId === 'player-1' ? 3 : 4), $player->normalSummonsUsed);
        }
        self::assertSame($snapshot, $duel->toArray());
        self::assertEquals($duel->rngState, $result->rngState);
    }

    public function test_does_not_draw_or_reorder_cards(): void
    {
        $duel = TestFactory::endDuel();
        $result = startNextTurn($duel, gxLegacyProfile());
        foreach ([0, 1] as $index) {
            self::assertSame($duel->players[$index]->mainDeck, $result->players[$index]->mainDeck);
            self::assertSame($duel->players[$index]->hand, $result->players[$index]->hand);
        }
    }

    public function test_rejects_invalid_end_state(): void
    {
        $duel = TestFactory::endDuel();
        foreach ([
            [$duel->with(['status' => DuelStatus::FINISHED]), 'Somente um Duelo ACTIVE pode iniciar o próximo turno.'],
            [$duel->with(['phase' => DuelPhase::MAIN_1]), 'O Duelo deve estar na fase END.'],
            [$duel->with(['turnNumber' => 0]), 'turnNumber deve ser um inteiro maior ou igual a 1.'],
            [$duel->with(['currentPlayerId' => null]), 'A Fase Final deve possuir jogador atual.'],
            [$duel->with(['resultReason' => DuelResultReason::DECK_OUT]), 'Um Duelo ACTIVE não pode possuir motivo de resultado.'],
        ] as [$state, $message]) {
            try {
                startNextTurn($state, gxLegacyProfile());
                self::fail();
            } catch (\Throwable $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }
}
