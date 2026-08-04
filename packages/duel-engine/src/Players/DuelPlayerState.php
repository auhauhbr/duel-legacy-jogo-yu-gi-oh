<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Players;

use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Zones\MonsterZones;
use DuelLegacy\DuelEngine\Zones\OrderedCardZone;
use DuelLegacy\DuelEngine\Zones\PlayerCardZones;
use DuelLegacy\DuelEngine\Zones\SpellTrapZones;
use InvalidArgumentException;

final readonly class DuelPlayerState
{
    public function __construct(
        public string $playerId,
        public int $lifePoints,
        public PlayerCardZones $cardZones,
        public MonsterZones $monsterZones,
        public SpellTrapZones $spellTrapZones,
        public ?string $fieldZone,
        public int $normalSummonsUsed,
        public int $normalSummonLimit,
    ) {}

    /** @param array<string, mixed> $changes */
    public function with(array $changes): self
    {
        foreach (['mainDeck', 'hand', 'graveyard', 'banishedFaceUp', 'banishedFaceDown', 'extraDeckFaceDown', 'extraDeckFaceUp'] as $legacyKey) {
            if (array_key_exists($legacyKey, $changes)) {
                throw new InvalidArgumentException('As zonas de cartas fora do campo devem ser alteradas por cardZones.');
            }
        }

        $monsterZones = $this->monsterZones;
        if (array_key_exists('monsterZones', $changes)) {
            if (! $changes['monsterZones'] instanceof MonsterZones) {
                throw new InvalidArgumentException('monsterZones deve ser uma instância de MonsterZones.');
            }
            $monsterZones = $changes['monsterZones'];
        }

        $spellTrapZones = $this->spellTrapZones;
        if (array_key_exists('spellTrapZones', $changes)) {
            if (! $changes['spellTrapZones'] instanceof SpellTrapZones) {
                throw new InvalidArgumentException('spellTrapZones deve ser uma instância de SpellTrapZones.');
            }
            $spellTrapZones = $changes['spellTrapZones'];
        }

        return new self(
            playerId: $changes['playerId'] ?? $this->playerId,
            lifePoints: $changes['lifePoints'] ?? $this->lifePoints,
            cardZones: $changes['cardZones'] ?? $this->cardZones,
            monsterZones: $monsterZones,
            spellTrapZones: $spellTrapZones,
            fieldZone: array_key_exists('fieldZone', $changes) ? $changes['fieldZone'] : $this->fieldZone,
            normalSummonsUsed: $changes['normalSummonsUsed'] ?? $this->normalSummonsUsed,
            normalSummonLimit: $changes['normalSummonLimit'] ?? $this->normalSummonLimit,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'playerId' => $this->playerId,
            'lifePoints' => $this->lifePoints,
            'mainDeck' => self::ids($this->cardZones->mainDeck),
            'hand' => self::ids($this->cardZones->hand),
            'graveyard' => self::ids($this->cardZones->graveyard),
            'banishedFaceUp' => self::ids($this->cardZones->banishedFaceUp),
            'banishedFaceDown' => self::ids($this->cardZones->banishedFaceDown),
            'extraDeckFaceDown' => self::ids($this->cardZones->extraDeckFaceDown),
            'extraDeckFaceUp' => self::ids($this->cardZones->extraDeckFaceUp),
            'monsterZones' => self::monsterZoneIds($this->monsterZones),
            'spellTrapZones' => self::spellTrapZoneIds($this->spellTrapZones),
            'fieldZone' => $this->fieldZone,
            'normalSummonsUsed' => $this->normalSummonsUsed,
            'normalSummonLimit' => $this->normalSummonLimit,
        ];
    }

    /** @return list<string> */
    private static function ids(OrderedCardZone $zone): array
    {
        return array_map(
            static fn (CardInstance $card): string => $card->id->value,
            $zone->cards(),
        );
    }

    /** @return list<?string> */
    private static function monsterZoneIds(MonsterZones $zones): array
    {
        return array_map(
            static fn (?CardInstance $card): ?string => $card?->id->value,
            $zones->slots(),
        );
    }

    /** @return list<?string> */
    private static function spellTrapZoneIds(SpellTrapZones $zones): array
    {
        return array_map(
            static fn (?CardInstance $card): ?string => $card?->id->value,
            $zones->slots(),
        );
    }
}
