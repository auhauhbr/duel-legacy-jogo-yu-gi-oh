<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Cards;

final readonly class TrapCardDefinition extends CardDefinition
{
    public function __construct(
        string $id,
        string $name,
        string $text,
        public TrapType $trapType,
    ) {
        parent::__construct($id, $name, $text, CardKind::TRAP);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [...$this->commonFields(), 'trapType' => $this->trapType->value];
    }
}
