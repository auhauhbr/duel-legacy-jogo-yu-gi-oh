<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Cards;

use DuelLegacy\DuelEngine\Internal\EcmaScriptString;
use InvalidArgumentException;

abstract readonly class CardDefinition
{
    protected function __construct(
        public string $id,
        public string $name,
        public string $text,
        public CardKind $kind,
    ) {
        if (EcmaScriptString::isBlank($id)) {
            throw new InvalidArgumentException('id não pode ser vazio.');
        }
        if (EcmaScriptString::isBlank($name)) {
            throw new InvalidArgumentException('name não pode ser vazio.');
        }
    }

    /** @return array{id: string, name: string, text: string, kind: string} */
    final protected function commonFields(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'text' => $this->text,
            'kind' => $this->kind->value,
        ];
    }

    /** @return array<string, int|string> */
    abstract public function toArray(): array;
}
