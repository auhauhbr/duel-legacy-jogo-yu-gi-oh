<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use DuelLegacy\DuelEngine\Zones\MonsterZones;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\createInitialDuelState;
use function DuelLegacy\DuelEngine\gxLegacyProfile;

final class CreateInitialDuelStateTest extends TestCase
{
    public function test_creates_preparing_duel_with_chosen_turn_order(): void
    {
        $players = [TestFactory::player('player-1'), TestFactory::player('player-2')];
        $duel = createInitialDuelState('duel-1', gxLegacyProfile(), 'engine-1', 'pool-1', $players, 'player-2');
        self::assertSame(['player-2', 'player-1'], $duel->turnOrder);
        self::assertSame('PREPARING', $duel->status->value);
        self::assertSame(0, $duel->turnNumber);
        self::assertNull($duel->currentPlayerId);
        self::assertNull($duel->phase);
        self::assertNull($duel->winnerId);
        self::assertNull($duel->resultReason);
        self::assertNull($duel->rngState);
        self::assertNotSame($players[0], $duel->players[0]);
        self::assertSame($players[0]->toArray(), $duel->players[0]->toArray());
    }

    /** @return iterable<array{string, string, string, string}> */
    public static function invalidText(): iterable
    {
        yield [' ', 'engine', 'pool', 'duelId não pode ser vazio.'];
        yield ['duel', ' ', 'pool', 'engineVersion não pode ser vazia.'];
        yield ['duel', 'engine', ' ', 'cardPoolVersion não pode ser vazia.'];
    }

    #[DataProvider('invalidText')]
    public function test_rejects_blank_metadata(string $duelId, string $engine, string $pool, string $message): void
    {
        $this->expectExceptionMessage($message);
        createInitialDuelState($duelId, gxLegacyProfile(), $engine, $pool, [TestFactory::player('p1'), TestFactory::player('p2')], 'p1');
    }

    public function test_requires_exactly_two_different_players_and_known_first_player(): void
    {
        foreach ([[], [TestFactory::player('p1')], [TestFactory::player('p1'), TestFactory::player('p2'), TestFactory::player('p3')]] as $players) {
            try {
                createInitialDuelState('d', gxLegacyProfile(), 'e', 'p', $players, 'p1');
                self::fail();
            } catch (InvalidArgumentException $exception) {
                self::assertSame('O Duelo deve possuir exatamente dois jogadores.', $exception->getMessage());
            }
        }
        $first = TestFactory::player('p1');
        $duplicate = TestFactory::player('p2')->with(['playerId' => 'p1']);
        try {
            createInitialDuelState('d', gxLegacyProfile(), 'e', 'p', [$first, $duplicate], 'p1');
            self::fail();
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Os jogadores devem possuir IDs diferentes.', $exception->getMessage());
        }
        $this->expectExceptionMessage('O jogador inicial deve pertencer ao Duelo.');
        createInitialDuelState('d', gxLegacyProfile(), 'e', 'p', [$first, TestFactory::player('p2')], 'unknown');
    }

    public function test_rejects_invalid_zones_and_global_duplicate_cards(): void
    {
        $first = TestFactory::player('p1');
        try {
            createInitialDuelState('d', gxLegacyProfile(), 'e', 'p', [$first->with(['monsterZones' => MonsterZones::empty(0)]), TestFactory::player('p2')], 'p1');
            self::fail();
        } catch (InvalidArgumentException $exception) {
            self::assertSame('As zonas do jogador são incompatíveis com o perfil.', $exception->getMessage());
        }
        $duplicate = TestFactory::withZoneIds(
            TestFactory::player('p2'),
            CardLocation::HAND,
            [$first->cardZones->mainDeck->cards()[0]->id->value],
        );
        $this->expectExceptionMessage('IDs de instância de carta devem ser únicos no Duelo.');
        createInitialDuelState('d', gxLegacyProfile(), 'e', 'p', [$first, $duplicate], 'p1');
    }
}
