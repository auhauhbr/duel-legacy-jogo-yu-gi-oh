<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests\Support;

use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Cards\SpellCardDefinition;
use DuelLegacy\DuelEngine\Cards\SpellType;
use DuelLegacy\DuelEngine\Duels\DuelState;
use DuelLegacy\DuelEngine\Duels\DuelStatus;
use DuelLegacy\DuelEngine\Identifiers\CardInstanceId;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Players\DuelPlayerState;
use DuelLegacy\DuelEngine\Rules\RulesProfile;
use DuelLegacy\DuelEngine\Zones\MonsterZones;
use DuelLegacy\DuelEngine\Zones\OrderedCardZone;
use DuelLegacy\DuelEngine\Zones\PlayerCardZones;

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
            self::cards(array_map(static fn (int $index): string => "{$playerId}-main-{$index}", range(1, $deckSize))),
            self::cards(["{$playerId}-extra-down"]),
        );
    }

    public static function richPlayer(string $playerId): DuelPlayerState
    {
        $player = self::player($playerId);
        $cardZones = $player->cardZones
            ->withZone(self::zone(CardLocation::HAND, self::cards(["{$playerId}-hand"])))
            ->withZone(self::zone(CardLocation::GRAVEYARD, self::cards(["{$playerId}-graveyard"])))
            ->withZone(self::zone(CardLocation::BANISHED_FACE_UP, self::cards(["{$playerId}-banished-up"])))
            ->withZone(self::zone(CardLocation::BANISHED_FACE_DOWN, self::cards(["{$playerId}-banished-down"])))
            ->withZone(self::zone(CardLocation::EXTRA_DECK_FACE_UP, self::cards(["{$playerId}-extra-up"])));

        return $player->with([
            'lifePoints' => $playerId === 'player-1' ? 7100 : 6200,
            'cardZones' => $cardZones,
            'monsterZones' => self::monsterZones([null, self::card("{$playerId}-monster"), null, null, null]),
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

    public static function card(string $instanceId): CardInstance
    {
        return new CardInstance(
            new CardInstanceId($instanceId),
            new SpellCardDefinition('test-definition', 'Carta fictícia de teste', '', SpellType::NORMAL),
        );
    }

    /**
     * @param  list<string>  $instanceIds
     * @return list<CardInstance>
     */
    public static function cards(array $instanceIds): array
    {
        return array_map(self::card(...), $instanceIds);
    }

    /** @param list<CardInstance> $cards */
    public static function zone(CardLocation $location, array $cards = []): OrderedCardZone
    {
        return new OrderedCardZone($location, $cards);
    }

    /** @param list<?CardInstance> $slots */
    public static function monsterZones(array $slots): MonsterZones
    {
        return new MonsterZones($slots);
    }

    /** @param list<string> $instanceIds */
    public static function withZoneIds(
        DuelPlayerState $player,
        CardLocation $location,
        array $instanceIds,
    ): DuelPlayerState {
        return $player->with([
            'cardZones' => $player->cardZones->withZone(self::zone($location, self::cards($instanceIds))),
        ]);
    }

    /** @return list<string> */
    public static function ids(OrderedCardZone $zone): array
    {
        return array_map(
            static fn (CardInstance $card): string => $card->id->value,
            $zone->cards(),
        );
    }

    /**
     * @param  list<CardInstance>  $mainDeck
     * @param  list<CardInstance>  $hand
     * @param  list<CardInstance>  $graveyard
     * @param  list<CardInstance>  $banishedFaceUp
     * @param  list<CardInstance>  $banishedFaceDown
     * @param  list<CardInstance>  $extraDeckFaceDown
     * @param  list<CardInstance>  $extraDeckFaceUp
     */
    public static function playerCardZones(
        array $mainDeck = [],
        array $hand = [],
        array $graveyard = [],
        array $banishedFaceUp = [],
        array $banishedFaceDown = [],
        array $extraDeckFaceDown = [],
        array $extraDeckFaceUp = [],
    ): PlayerCardZones {
        return new PlayerCardZones(
            mainDeck: self::zone(CardLocation::MAIN_DECK, $mainDeck),
            hand: self::zone(CardLocation::HAND, $hand),
            graveyard: self::zone(CardLocation::GRAVEYARD, $graveyard),
            banishedFaceUp: self::zone(CardLocation::BANISHED_FACE_UP, $banishedFaceUp),
            banishedFaceDown: self::zone(CardLocation::BANISHED_FACE_DOWN, $banishedFaceDown),
            extraDeckFaceDown: self::zone(CardLocation::EXTRA_DECK_FACE_DOWN, $extraDeckFaceDown),
            extraDeckFaceUp: self::zone(CardLocation::EXTRA_DECK_FACE_UP, $extraDeckFaceUp),
        );
    }
}
