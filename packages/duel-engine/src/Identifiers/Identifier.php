<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Identifiers;

use InvalidArgumentException;

abstract readonly class Identifier
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('O identificador não pode ser vazio.');
        }
    }

    final public function __toString(): string
    {
        return $this->value;
    }
}
