<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests\Support;

use DuelLegacy\DuelEngine\Duels\DuelState;
use DuelLegacy\DuelEngine\Duels\DuelStatus;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Players\DuelPlayerState;
use DuelLegacy\DuelEngine\Rules\RulesProfile;

use function DuelLegacy\DuelEngine\createDeterministicRng;
use function DuelLegacy\DuelEngine\createInitialDuelState;
use function DuelLegacy\DuelEngine\createInitialPlayerState;
use function DuelLegacy\DuelEngine\gxLegacyProfile;
use function DuelLegacy\DuelEngine\prepareInitialDuelState;
use function DuelLegacy\DuelEngine\transitionFromMainPhaseOne;

final class TestFactory
{
    public static function player(string $playerId, int $deckSize = 10): DuelPlayerState
    {
        return createInitialPlayerState(
            gxLegacyProfile(),
            $playerId,
            array_map(static fn (int $index): string => "{$playerId}-main-{$index}", range(1, $deckSize)),
            ["{$playerId}-extra-down"],
        );
    }

    public static function richPlayer(string $playerId): DuelPlayerState
    {
        return self::player($playerId)->with([
            'lifePoints' => $playerId === 'player-1' ? 7100 : 6200,
            'hand' => ["{$playerId}-hand"],
            'graveyard' => ["{$playerId}-graveyard"],
            'banishedFaceUp' => ["{$playerId}-banished-up"],
            'banishedFaceDown' => ["{$playerId}-banished-down"],
            'extraDeckFaceUp' => ["{$playerId}-extra-up"],
            'monsterZones' => [null, "{$playerId}-monster", null, null, null],
            'spellTrapZones' => [null, null, "{$playerId}-spell", null, null],
            'fieldZone' => "{$playerId}-field",
            'normalSummonsUsed' => $playerId === 'player-1' ? 1 : 0,
            'normalSummonLimit' => $playerId === 'player-1' ? 2 : 1,
        ]);
    }

    public static function initialDuel(string $firstPlayerId = 'player-1', int $deckSize = 10): DuelState
    {
        return createInitialDuelState(
            'duel-test',
            gxLegacyProfile(),
            'engine-test',
            'pool-test',
            [self::player('player-1', $deckSize), self::player('player-2', $deckSize)],
            $firstPlayerId,
        );
    }

    public static function preparedDuel(string $firstPlayerId = 'player-1'): DuelState
    {
        return prepareInitialDuelState(self::initialDuel($firstPlayerId), gxLegacyProfile(), 'prepared-seed');
    }

    public static function activeDuel(DuelPhase $phase, int $turnNumber = 1): DuelState
    {
        $turnOrder = ['player-1', 'player-2'];

        return new DuelState(
            duelId: 'duel-active',
            rulesProfileId: gxLegacyProfile()->id,
            engineVersion: 'engine-active',
            cardPoolVersion: 'pool-active',
            players: [self::richPlayer('player-1'), self::richPlayer('player-2')],
            turnOrder: $turnOrder,
            rngState: createDeterministicRng('active-seed'),
            status: DuelStatus::ACTIVE,
            turnNumber: $turnNumber,
            currentPlayerId: $turnOrder[($turnNumber - 1) % 2],
            phase: $phase,
            winnerId: null,
            resultReason: null,
        );
    }

    public static function endDuel(int $turnNumber = 1): DuelState
    {
        return transitionFromMainPhaseOne(self::activeDuel(DuelPhase::MAIN_1, $turnNumber), gxLegacyProfile(), DuelPhase::END);
    }

    /** @param array<string, mixed> $changes */
    public static function profile(array $changes = []): RulesProfile
    {
        return gxLegacyProfile()->with($changes);
    }
}
