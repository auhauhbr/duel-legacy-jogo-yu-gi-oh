<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Internal\EcmaScriptString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EcmaScriptStringTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function ecmaScriptWhitespace(): iterable
    {
        $codePoints = [
            0x0009, 0x000A, 0x000B, 0x000C, 0x000D, 0x0020, 0x00A0, 0x1680,
            0x2000, 0x2001, 0x2002, 0x2003, 0x2004, 0x2005, 0x2006, 0x2007,
            0x2008, 0x2009, 0x200A, 0x2028, 0x2029, 0x202F, 0x205F, 0x3000,
            0xFEFF,
        ];
        foreach ($codePoints as $codePoint) {
            $character = json_decode('"\\u'.str_pad(dechex($codePoint), 4, '0', STR_PAD_LEFT).'"', true, 512, JSON_THROW_ON_ERROR);
            self::assertIsString($character);
            yield sprintf('U+%04X', $codePoint) => [$character];
        }
    }

    #[DataProvider('ecmaScriptWhitespace')]
    public function test_recognizes_every_ecma_script_trim_character(string $character): void
    {
        self::assertTrue(EcmaScriptString::isBlank($character));
        self::assertTrue(EcmaScriptString::isBlank($character." \t\n".$character));
    }

    public function test_does_not_depend_on_php_trim_or_accept_non_ecma_script_whitespace(): void
    {
        self::assertTrue(EcmaScriptString::isBlank("\u{00A0}\u{2003}\u{FEFF}"));
        self::assertFalse(EcmaScriptString::isBlank("\u{0085}"));
        self::assertFalse(EcmaScriptString::isBlank("\u{200B}"));
        self::assertFalse(EcmaScriptString::isBlank(" \u{0085} "));
        self::assertFalse(EcmaScriptString::isBlank("\u{00A0}seed válida\u{FEFF}"));
    }
}
