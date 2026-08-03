<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Random;

/** @template-covariant TValue of int|float */
final readonly class RandomResult
{
    /** @param TValue $value */
    public function __construct(
        public int|float $value,
        public DeterministicRngState $nextState,
    ) {}

    /** @return array{value: TValue, nextState: array{seed: string, state: int, calls: int}} */
    public function toArray(): array
    {
        return ['value' => $this->value, 'nextState' => $this->nextState->toArray()];
    }
}
