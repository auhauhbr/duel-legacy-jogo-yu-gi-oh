<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardDefinition;
use DuelLegacy\DuelEngine\Cards\CardKind;
use DuelLegacy\DuelEngine\Cards\MonsterAttribute;
use DuelLegacy\DuelEngine\Cards\MonsterCardDefinition;
use DuelLegacy\DuelEngine\Cards\MonsterCategory;
use DuelLegacy\DuelEngine\Cards\SpellCardDefinition;
use DuelLegacy\DuelEngine\Cards\SpellType;
use DuelLegacy\DuelEngine\Cards\TrapCardDefinition;
use DuelLegacy\DuelEngine\Cards\TrapType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CardDefinitionCommonFieldsTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function blankCommonFields(): iterable
    {
        $values = [
            'vazio' => '',
            'espaço ASCII' => ' ',
            'tabulação' => "\t",
            'quebra de linha' => "\n",
            'NBSP' => "\u{00A0}",
            'U+1680' => "\u{1680}",
            'U+2000' => "\u{2000}",
            'U+200A' => "\u{200A}",
            'U+2028' => "\u{2028}",
            'U+2029' => "\u{2029}",
            'U+202F' => "\u{202F}",
            'U+205F' => "\u{205F}",
            'U+3000' => "\u{3000}",
            'U+FEFF' => "\u{FEFF}",
        ];

        foreach ($values as $label => $value) {
            yield "id: {$label}" => ['id', $value, 'id não pode ser vazio.'];
            yield "name: {$label}" => ['name', $value, 'name não pode ser vazio.'];
        }
    }

    #[DataProvider('blankCommonFields')]
    public function test_rejects_empty_or_ecma_script_blank_common_fields(
        string $field,
        string $value,
        string $message,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        if ($field === 'id') {
            new SpellCardDefinition($value, 'Carta fictícia', '', SpellType::NORMAL);
        } else {
            new SpellCardDefinition('definition-1', $value, '', SpellType::NORMAL);
        }
    }

    public function test_preserves_valid_common_fields_and_empty_or_filled_text_exactly(): void
    {
        $emptyText = new SpellCardDefinition(' definition-1 ', ' Carta fictícia ', '', SpellType::NORMAL);
        $filledText = new TrapCardDefinition('definition-2', 'Outra carta', " Linha 1\nLinha 2 ", TrapType::COUNTER);

        self::assertSame(' definition-1 ', $emptyText->id);
        self::assertSame(' Carta fictícia ', $emptyText->name);
        self::assertSame('', $emptyText->text);
        self::assertSame(" Linha 1\nLinha 2 ", $filledText->text);
        self::assertSame(CardKind::SPELL, $emptyText->kind);
        self::assertSame(CardKind::TRAP, $filledText->kind);
    }

    public function test_concrete_definitions_share_the_card_definition_contract(): void
    {
        $definitions = [
            new MonsterCardDefinition('monster-1', 'Monstro fictício', '', MonsterAttribute::DARK, 'Dragon', 4, 1500, 1200, MonsterCategory::NORMAL),
            new SpellCardDefinition('spell-1', 'Magia fictícia', '', SpellType::NORMAL),
            new TrapCardDefinition('trap-1', 'Armadilha fictícia', '', TrapType::NORMAL),
        ];

        self::assertTrue((new \ReflectionClass(CardDefinition::class))->isAbstract());
        foreach ($definitions as $definition) {
            self::assertInstanceOf(CardDefinition::class, $definition);
            self::assertArrayHasKey('id', $definition->toArray());
            self::assertArrayHasKey('kind', $definition->toArray());
        }
    }
}
