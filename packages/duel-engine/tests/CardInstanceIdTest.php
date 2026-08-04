<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Identifiers\CardInstanceId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class CardInstanceIdTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function ecmaScriptBlankValues(): iterable
    {
        yield 'string vazia' => [''];
        yield 'U+0009 CHARACTER TABULATION' => ["\u{0009}"];
        yield 'U+000A LINE FEED' => ["\u{000A}"];
        yield 'U+000B LINE TABULATION' => ["\u{000B}"];
        yield 'U+000C FORM FEED' => ["\u{000C}"];
        yield 'U+000D CARRIAGE RETURN' => ["\u{000D}"];
        yield 'U+0020 SPACE' => ["\u{0020}"];
        yield 'U+00A0 NO-BREAK SPACE' => ["\u{00A0}"];
        yield 'U+1680 OGHAM SPACE MARK' => ["\u{1680}"];
        yield 'U+2000 EN QUAD' => ["\u{2000}"];
        yield 'U+2001 EM QUAD' => ["\u{2001}"];
        yield 'U+2002 EN SPACE' => ["\u{2002}"];
        yield 'U+2003 EM SPACE' => ["\u{2003}"];
        yield 'U+2004 THREE-PER-EM SPACE' => ["\u{2004}"];
        yield 'U+2005 FOUR-PER-EM SPACE' => ["\u{2005}"];
        yield 'U+2006 SIX-PER-EM SPACE' => ["\u{2006}"];
        yield 'U+2007 FIGURE SPACE' => ["\u{2007}"];
        yield 'U+2008 PUNCTUATION SPACE' => ["\u{2008}"];
        yield 'U+2009 THIN SPACE' => ["\u{2009}"];
        yield 'U+200A HAIR SPACE' => ["\u{200A}"];
        yield 'U+2028 LINE SEPARATOR' => ["\u{2028}"];
        yield 'U+2029 PARAGRAPH SEPARATOR' => ["\u{2029}"];
        yield 'U+202F NARROW NO-BREAK SPACE' => ["\u{202F}"];
        yield 'U+205F MEDIUM MATHEMATICAL SPACE' => ["\u{205F}"];
        yield 'U+3000 IDEOGRAPHIC SPACE' => ["\u{3000}"];
        yield 'U+FEFF ZERO WIDTH NO-BREAK SPACE' => ["\u{FEFF}"];
        yield 'combinação de whitespaces ECMAScript' => ["\u{0009}\u{0020}\u{00A0}\u{1680}\u{2003}\u{2028}\u{2029}\u{202F}\u{205F}\u{3000}\u{FEFF}"];
    }

    /** @return iterable<string, array{string}> */
    public static function validValues(): iterable
    {
        yield 'ID simples' => ['card001'];
        yield 'ID com hífen' => ['card-001'];
        yield 'ID com underscore' => ['player_1_card_001'];
        yield 'zero' => ['0'];
        yield 'Unicode não vazio' => ['éxemplo'];
        yield 'espaço interno' => ['card 001'];
        yield 'espaço ASCII nas extremidades' => [' carta-001 '];
        yield 'NBSP nas extremidades' => ["\u{00A0}card-001\u{00A0}"];
        yield 'whitespace Unicode nas extremidades' => ["\u{2003}card-001\u{FEFF}"];
        yield 'U+0085 não é whitespace ECMAScript' => ["\u{0085}"];
        yield 'U+200B não é whitespace ECMAScript' => ["\u{200B}"];
        yield 'IDs visualmente semelhantes permanecem distintos' => ['card‐001'];
    }

    #[DataProvider('ecmaScriptBlankValues')]
    public function test_rejects_empty_or_ecma_script_whitespace_only_values(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O identificador não pode ser vazio.');

        new CardInstanceId($value);
    }

    #[DataProvider('validValues')]
    public function test_preserves_every_valid_value_exactly(string $value): void
    {
        $first = new CardInstanceId($value);
        $second = new CardInstanceId($value);

        self::assertSame($value, $first->value);
        self::assertSame($value, (string) $first);
        self::assertSame($first->value, $second->value);
        self::assertSame((string) $first, (string) $second);
    }

    public function test_is_readonly_and_its_value_cannot_be_replaced(): void
    {
        $id = new CardInstanceId('card-001');
        $reflection = new ReflectionClass($id);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());

        try {
            (new ReflectionProperty($id, 'value'))->setValue($id, 'replacement');
            self::fail('O valor readonly de CardInstanceId foi substituído.');
        } catch (\Error) {
            self::assertSame('card-001', $id->value);
            self::assertSame('card-001', (string) $id);
        }
    }
}
