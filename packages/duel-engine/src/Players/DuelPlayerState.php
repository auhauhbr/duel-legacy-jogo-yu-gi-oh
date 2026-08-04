<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Players;

use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Zones\OrderedCardZone;
use DuelLegacy\DuelEngine\Zones\PlayerCardZones;
use InvalidArgumentException;

final readonly class DuelPlayerState
{
    /**
     * @param  list<?string>  $monsterZones
     * @param  list<?string>  $spellTrapZones
     */
    public function __construct(
        public string $playerId,
        public int $lifePoints,
        public PlayerCardZones $cardZones,
        public array $monsterZones,
        public array $spellTrapZones,
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

        return new self(
            playerId: $changes['playerId'] ?? $this->playerId,
            lifePoints: $changes['lifePoints'] ?? $this->lifePoints,
            cardZones: $changes['cardZones'] ?? $this->cardZones,
            monsterZones: $changes['monsterZones'] ?? $this->monsterZones,
            spellTrapZones: $changes['spellTrapZones'] ?? $this->spellTrapZones,
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
            'monsterZones' => $this->monsterZones,
            'spellTrapZones' => $this->spellTrapZones,
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
}
