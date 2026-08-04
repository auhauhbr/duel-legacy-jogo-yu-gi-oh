<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Identifiers;

use DuelLegacy\DuelEngine\Internal\EcmaScriptString;

final readonly class CardInstanceId extends Identifier
{
    protected static function isBlank(string $value): bool
    {
        return EcmaScriptString::isBlank($value);
    }
}
