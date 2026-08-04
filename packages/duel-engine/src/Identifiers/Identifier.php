<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Identifiers;

use InvalidArgumentException;

abstract readonly class Identifier
{
    public function __construct(public string $value)
    {
        if (static::isBlank($value)) {
            throw new InvalidArgumentException('O identificador não pode ser vazio.');
        }
    }

    protected static function isBlank(string $value): bool
    {
        return trim($value) === '';
    }

    final public function __toString(): string
    {
        return $this->value;
    }
}
