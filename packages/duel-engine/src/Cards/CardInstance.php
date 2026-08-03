<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Cards;

use DuelLegacy\DuelEngine\Identifiers\CardInstanceId;

final readonly class CardInstance
{
    public function __construct(
        public CardInstanceId $id,
        public CardDefinition $definition,
    ) {}

    /** @return array{id: string, definition: array<string, int|string>} */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value,
            'definition' => $this->definition->toArray(),
        ];
    }
}
