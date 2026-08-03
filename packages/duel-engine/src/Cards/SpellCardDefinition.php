<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Cards;

final readonly class SpellCardDefinition extends CardDefinition
{
    public function __construct(
        string $id,
        string $name,
        string $text,
        public SpellType $spellType,
    ) {
        parent::__construct($id, $name, $text, CardKind::SPELL);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [...$this->commonFields(), 'spellType' => $this->spellType->value];
    }
}
