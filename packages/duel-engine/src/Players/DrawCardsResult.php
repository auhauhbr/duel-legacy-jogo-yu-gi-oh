<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Players;

final readonly class DrawCardsResult
{
    /** @param list<string> $drawnCardIds */
    public function __construct(
        public DuelPlayerState $playerState,
        public array $drawnCardIds,
    ) {}
}
