<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Internal;

/** @internal */
final class EcmaScriptString
{
    /**
     * Os code points são exatamente o conjunto WhiteSpace + LineTerminator
     * removido por String.prototype.trim() no ECMAScript atual.
     */
    private const string TRIMMED_WHITESPACE = '\\x{0009}\\x{000A}\\x{000B}\\x{000C}\\x{000D}\\x{0020}\\x{00A0}\\x{1680}\\x{2000}-\\x{200A}\\x{2028}\\x{2029}\\x{202F}\\x{205F}\\x{3000}\\x{FEFF}';

    public static function isBlank(string $value): bool
    {
        return preg_match('/^['.self::TRIMMED_WHITESPACE.']*$/u', $value) === 1;
    }
}
