<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Players;

final readonly class DuelPlayerState
{
    /**
     * @param  list<string>  $mainDeck
     * @param  list<string>  $hand
     * @param  list<string>  $graveyard
     * @param  list<string>  $banishedFaceUp
     * @param  list<string>  $banishedFaceDown
     * @param  list<string>  $extraDeckFaceDown
     * @param  list<string>  $extraDeckFaceUp
     * @param  list<?string>  $monsterZones
     * @param  list<?string>  $spellTrapZones
     */
    public function __construct(
        public string $playerId,
        public int $lifePoints,
        public array $mainDeck,
        public array $hand,
        public array $graveyard,
        public array $banishedFaceUp,
        public array $banishedFaceDown,
        public array $extraDeckFaceDown,
        public array $extraDeckFaceUp,
        public array $monsterZones,
        public array $spellTrapZones,
        public ?string $fieldZone,
        public int $normalSummonsUsed,
        public int $normalSummonLimit,
    ) {}

    /** @param array<string, mixed> $changes */
    public function with(array $changes): self
    {
        return new self(
            playerId: $changes['playerId'] ?? $this->playerId,
            lifePoints: $changes['lifePoints'] ?? $this->lifePoints,
            mainDeck: $changes['mainDeck'] ?? $this->mainDeck,
            hand: $changes['hand'] ?? $this->hand,
            graveyard: $changes['graveyard'] ?? $this->graveyard,
            banishedFaceUp: $changes['banishedFaceUp'] ?? $this->banishedFaceUp,
            banishedFaceDown: $changes['banishedFaceDown'] ?? $this->banishedFaceDown,
            extraDeckFaceDown: $changes['extraDeckFaceDown'] ?? $this->extraDeckFaceDown,
            extraDeckFaceUp: $changes['extraDeckFaceUp'] ?? $this->extraDeckFaceUp,
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
            'mainDeck' => $this->mainDeck,
            'hand' => $this->hand,
            'graveyard' => $this->graveyard,
            'banishedFaceUp' => $this->banishedFaceUp,
            'banishedFaceDown' => $this->banishedFaceDown,
            'extraDeckFaceDown' => $this->extraDeckFaceDown,
            'extraDeckFaceUp' => $this->extraDeckFaceUp,
            'monsterZones' => $this->monsterZones,
            'spellTrapZones' => $this->spellTrapZones,
            'fieldZone' => $this->fieldZone,
            'normalSummonsUsed' => $this->normalSummonsUsed,
            'normalSummonLimit' => $this->normalSummonLimit,
        ];
    }
}
