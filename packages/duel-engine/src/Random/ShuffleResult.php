<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Random;

/** @template-covariant T */
final readonly class ShuffleResult
{
    /** @param list<T> $items */
    public function __construct(
        public array $items,
        public DeterministicRngState $nextState,
    ) {}

    /** @return array{items: list<T>, nextState: array{seed: string, state: int, calls: int}} */
    public function toArray(): array
    {
        return ['items' => $this->items, 'nextState' => $this->nextState->toArray()];
    }
}
