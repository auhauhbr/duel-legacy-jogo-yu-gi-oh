<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Random;

final readonly class DeterministicRngState
{
    public function __construct(
        public string $seed,
        public int $state,
        public int $calls,
    ) {}

    /** @return array{seed: string, state: int, calls: int} */
    public function toArray(): array
    {
        return ['seed' => $this->seed, 'state' => $this->state, 'calls' => $this->calls];
    }
}
