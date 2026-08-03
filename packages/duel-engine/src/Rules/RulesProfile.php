<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Rules;

use InvalidArgumentException;

final readonly class RulesProfile
{
    /**
     * @param  list<SummonMethod>  $enabledSummons
     */
    public function __construct(
        public string $id,
        public int|float $startingLifePoints,
        public int|float $startingHandSize,
        public int|float $handLimit,
        public int|float $mainDeckMin,
        public int|float $mainDeckMax,
        public int|float $extraDeckMax,
        public int|float $sideDeckMax,
        public int|float $mainMonsterZones,
        public int|float $spellTrapZones,
        public bool $hasFieldZone,
        public bool $hasExtraMonsterZones,
        public bool $hasPendulumZones,
        public bool $hasSkillCard,
        public bool $drawOnFirstTurn,
        public bool $battleOnFirstTurn,
        public array $enabledSummons,
    ) {
        self::assertSummonMethods($enabledSummons);
    }

    /** @param array<array-key, mixed> $enabledSummons */
    private static function assertSummonMethods(array $enabledSummons): void
    {
        foreach ($enabledSummons as $method) {
            if (! $method instanceof SummonMethod) {
                throw new InvalidArgumentException('enabledSummons deve conter apenas SummonMethod.');
            }
        }
    }

    /** @param array<string, mixed> $changes */
    public function with(array $changes): self
    {
        return new self(
            id: $changes['id'] ?? $this->id,
            startingLifePoints: $changes['startingLifePoints'] ?? $this->startingLifePoints,
            startingHandSize: $changes['startingHandSize'] ?? $this->startingHandSize,
            handLimit: $changes['handLimit'] ?? $this->handLimit,
            mainDeckMin: $changes['mainDeckMin'] ?? $this->mainDeckMin,
            mainDeckMax: $changes['mainDeckMax'] ?? $this->mainDeckMax,
            extraDeckMax: $changes['extraDeckMax'] ?? $this->extraDeckMax,
            sideDeckMax: $changes['sideDeckMax'] ?? $this->sideDeckMax,
            mainMonsterZones: $changes['mainMonsterZones'] ?? $this->mainMonsterZones,
            spellTrapZones: $changes['spellTrapZones'] ?? $this->spellTrapZones,
            hasFieldZone: $changes['hasFieldZone'] ?? $this->hasFieldZone,
            hasExtraMonsterZones: $changes['hasExtraMonsterZones'] ?? $this->hasExtraMonsterZones,
            hasPendulumZones: $changes['hasPendulumZones'] ?? $this->hasPendulumZones,
            hasSkillCard: $changes['hasSkillCard'] ?? $this->hasSkillCard,
            drawOnFirstTurn: $changes['drawOnFirstTurn'] ?? $this->drawOnFirstTurn,
            battleOnFirstTurn: $changes['battleOnFirstTurn'] ?? $this->battleOnFirstTurn,
            enabledSummons: $changes['enabledSummons'] ?? $this->enabledSummons,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'startingLifePoints' => $this->startingLifePoints,
            'startingHandSize' => $this->startingHandSize,
            'handLimit' => $this->handLimit,
            'mainDeckMin' => $this->mainDeckMin,
            'mainDeckMax' => $this->mainDeckMax,
            'extraDeckMax' => $this->extraDeckMax,
            'sideDeckMax' => $this->sideDeckMax,
            'mainMonsterZones' => $this->mainMonsterZones,
            'spellTrapZones' => $this->spellTrapZones,
            'hasFieldZone' => $this->hasFieldZone,
            'hasExtraMonsterZones' => $this->hasExtraMonsterZones,
            'hasPendulumZones' => $this->hasPendulumZones,
            'hasSkillCard' => $this->hasSkillCard,
            'drawOnFirstTurn' => $this->drawOnFirstTurn,
            'battleOnFirstTurn' => $this->battleOnFirstTurn,
            'enabledSummons' => array_map(static fn (SummonMethod $method): string => $method->value, $this->enabledSummons),
        ];
    }
}
