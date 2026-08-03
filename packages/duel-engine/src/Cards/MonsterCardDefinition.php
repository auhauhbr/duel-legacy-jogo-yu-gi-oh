<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Cards;

use DuelLegacy\DuelEngine\Internal\EcmaScriptString;
use InvalidArgumentException;

final readonly class MonsterCardDefinition extends CardDefinition
{
    public function __construct(
        string $id,
        string $name,
        string $text,
        public MonsterAttribute $attribute,
        public string $monsterType,
        public int $level,
        public int $atk,
        public int $def,
        public MonsterCategory $monsterCategory,
    ) {
        parent::__construct($id, $name, $text, CardKind::MONSTER);

        if (EcmaScriptString::isBlank($monsterType)) {
            throw new InvalidArgumentException('monsterType não pode ser vazio.');
        }
        if ($level < 1 || $level > 12) {
            throw new InvalidArgumentException('level deve estar entre 1 e 12.');
        }
        if ($atk < 0) {
            throw new InvalidArgumentException('atk não pode ser negativo.');
        }
        if ($def < 0) {
            throw new InvalidArgumentException('def não pode ser negativa.');
        }
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            ...$this->commonFields(),
            'attribute' => $this->attribute->value,
            'monsterType' => $this->monsterType,
            'level' => $this->level,
            'atk' => $this->atk,
            'def' => $this->def,
            'monsterCategory' => $this->monsterCategory->value,
        ];
    }
}
