<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Duels\DuelStatus;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\gxLegacyProfile;
use function DuelLegacy\DuelEngine\prepareInitialDuelState;

final class PrepareInitialDuelStateTest extends TestCase
{
    public function test_shuffles_by_turn_order_and_deals_initial_hands_without_extra_rng_calls(): void
    {
        $initial = TestFactory::initialDuel('player-2');
        $snapshot = $initial->toArray();
        $prepared = prepareInitialDuelState($initial, gxLegacyProfile(), 'prepare 🔥');
        self::assertSame(DuelStatus::PREPARING, $prepared->status);
        self::assertSame(0, $prepared->turnNumber);
        self::assertNull($prepared->phase);
        self::assertNull($prepared->currentPlayerId);
        self::assertSame(18, $prepared->rngState?->calls);
        foreach ($prepared->players as $player) {
            self::assertSame(5, $player->cardZones->hand->count());
            self::assertSame(5, $player->cardZones->mainDeck->count());
        }
        self::assertSame($snapshot, $initial->toArray());
        self::assertSame($prepared->toArray(), prepareInitialDuelState($initial, gxLegacyProfile(), 'prepare 🔥')->toArray());
    }

    public function test_rejects_invalid_preparation_states(): void
    {
        $initial = TestFactory::initialDuel();
        $cases = [
            [$initial->with(['status' => DuelStatus::ACTIVE]), gxLegacyProfile(), 'seed', 'Somente um Duelo em PREPARING pode ser preparado.'],
            [$initial->with(['rngState' => \DuelLegacy\DuelEngine\createDeterministicRng('x')]), gxLegacyProfile(), 'seed', 'O Duelo já possui um estado de RNG.'],
            [$initial, TestFactory::profile(['id' => 'OTHER']), 'seed', 'RulesProfile incompatível com o Duelo.'],
            [$initial, gxLegacyProfile(), ' ', 'A seed não pode ser vazia.'],
            [$initial->with(['turnOrder' => ['player-1', 'unknown']]), gxLegacyProfile(), 'seed', 'turnOrder é incompatível com os jogadores do Duelo.'],
        ];
        foreach ($cases as [$state, $profile, $seed, $message]) {
            try {
                prepareInitialDuelState($state, $profile, $seed);
                self::fail();
            } catch (\Throwable $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function test_rejects_insufficient_deck_and_duplicate_card_ids(): void
    {
        $initial = TestFactory::initialDuel();
        $players = $initial->players;
        $players[0] = $players[0]->with(['cardZones' => $players[0]->cardZones->withZone(
            TestFactory::zone(CardLocation::MAIN_DECK, array_slice($players[0]->cardZones->mainDeck->cards(), 0, 4)),
        )]);
        try {
            prepareInitialDuelState($initial->with(['players' => $players]), gxLegacyProfile(), 'seed');
            self::fail();
        } catch (\Throwable $exception) {
            self::assertSame('Jogador sem cartas suficientes para a mão inicial.', $exception->getMessage());
        }
        $players = $initial->players;
        $players[1] = TestFactory::withZoneIds($players[1], CardLocation::HAND, [$players[0]->cardZones->mainDeck->cards()[0]->id->value]);
        $this->expectExceptionMessage('IDs de instância de carta devem ser únicos no Duelo.');
        prepareInitialDuelState($initial->with(['players' => $players]), gxLegacyProfile(), 'seed');
    }
}
