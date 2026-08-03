<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardKind;
use DuelLegacy\DuelEngine\Cards\MonsterAttribute;
use DuelLegacy\DuelEngine\Cards\MonsterCardDefinition;
use DuelLegacy\DuelEngine\Cards\MonsterCategory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MonsterCardDefinitionTest extends TestCase
{
    /** @return iterable<string, array{MonsterCategory}> */
    public static function monsterCategories(): iterable
    {
        foreach (MonsterCategory::cases() as $category) {
            yield $category->value => [$category];
        }
    }

    #[DataProvider('monsterCategories')]
    public function test_constructs_every_gx_monster_category(MonsterCategory $category): void
    {
        $definition = self::definition(monsterCategory: $category);

        self::assertSame($category, $definition->monsterCategory);
        self::assertSame(CardKind::MONSTER, $definition->kind);
        self::assertSame($category->value, $definition->toArray()['monsterCategory']);
    }

    public function test_constructs_normal_and_effect_monsters_with_exact_structural_data(): void
    {
        $normal = self::definition(
            id: ' fictional-normal ',
            name: ' Monstro Normal Fictício ',
            text: '',
            attribute: MonsterAttribute::EARTH,
            monsterType: ' Warrior ',
            level: 1,
            atk: 0,
            def: 0,
            monsterCategory: MonsterCategory::NORMAL,
        );
        $effect = self::definition(
            id: 'fictional-effect',
            name: 'Monstro de Efeito Fictício',
            text: ' Texto estrutural fictício. ',
            attribute: MonsterAttribute::LIGHT,
            monsterType: 'Spellcaster',
            level: 12,
            atk: 3200,
            def: 2800,
            monsterCategory: MonsterCategory::EFFECT,
        );

        self::assertSame([
            'id' => ' fictional-normal ',
            'name' => ' Monstro Normal Fictício ',
            'text' => '',
            'kind' => 'MONSTER',
            'attribute' => 'EARTH',
            'monsterType' => ' Warrior ',
            'level' => 1,
            'atk' => 0,
            'def' => 0,
            'monsterCategory' => 'NORMAL',
        ], $normal->toArray());
        self::assertSame(MonsterAttribute::LIGHT, $effect->attribute);
        self::assertSame('Spellcaster', $effect->monsterType);
        self::assertSame(12, $effect->level);
        self::assertSame(3200, $effect->atk);
        self::assertSame(2800, $effect->def);
        self::assertSame(' Texto estrutural fictício. ', $effect->text);
        self::assertSame(MonsterCategory::EFFECT, $effect->monsterCategory);
    }

    /** @return iterable<string, array{int, int, int, string}> */
    public static function invalidNumericFields(): iterable
    {
        yield 'nível zero' => [0, 0, 0, 'level deve estar entre 1 e 12.'];
        yield 'nível treze' => [13, 0, 0, 'level deve estar entre 1 e 12.'];
        yield 'nível negativo' => [-1, 0, 0, 'level deve estar entre 1 e 12.'];
        yield 'ATK negativo' => [4, -1, 0, 'atk não pode ser negativo.'];
        yield 'DEF negativa' => [4, 0, -1, 'def não pode ser negativa.'];
    }

    #[DataProvider('invalidNumericFields')]
    public function test_rejects_invalid_numeric_fields(int $level, int $atk, int $def, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        self::definition(level: $level, atk: $atk, def: $def);
    }

    /** @return iterable<string, array{string}> */
    public static function blankMonsterTypes(): iterable
    {
        yield 'vazio' => [''];
        yield 'ASCII' => [' '];
        yield 'tabulação' => ["\t"];
        yield 'quebra de linha' => ["\n"];
        yield 'NBSP' => ["\u{00A0}"];
        yield 'U+1680' => ["\u{1680}"];
        yield 'U+2000' => ["\u{2000}"];
        yield 'U+200A' => ["\u{200A}"];
        yield 'U+2028' => ["\u{2028}"];
        yield 'U+2029' => ["\u{2029}"];
        yield 'U+202F' => ["\u{202F}"];
        yield 'U+205F' => ["\u{205F}"];
        yield 'U+3000' => ["\u{3000}"];
        yield 'U+FEFF' => ["\u{FEFF}"];
    }

    #[DataProvider('blankMonsterTypes')]
    public function test_rejects_empty_or_ecma_script_blank_monster_type(string $monsterType): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('monsterType não pode ser vazio.');
        self::definition(monsterType: $monsterType);
    }

    public function test_requires_attribute_and_category_native_enum_values(): void
    {
        $constructor = new \ReflectionClass(MonsterCardDefinition::class);
        $base = ['monster-1', 'Monstro fictício', '', MonsterAttribute::DARK, 'Dragon', 4, 1000, 1000, MonsterCategory::EFFECT];
        $rejections = 0;

        foreach ([3, 8] as $invalidIndex) {
            $arguments = $base;
            $arguments[$invalidIndex] = null;
            try {
                $constructor->newInstanceArgs($arguments);
                self::fail('Enum obrigatório ausente foi aceito.');
            } catch (\TypeError) {
                $rejections++;
            }
        }

        self::assertSame(2, $rejections);
    }

    public function test_is_readonly_and_equivalent_inputs_produce_equivalent_data(): void
    {
        $first = self::definition();
        $second = self::definition();

        self::assertNotSame($first, $second);
        self::assertSame($first->toArray(), $second->toArray());
        self::assertTrue((new \ReflectionClass($first))->isReadOnly());

        try {
            $property = new \ReflectionProperty($first, 'atk');
            $property->setValue($first, 9999);
            self::fail('Definição readonly foi alterada.');
        } catch (\Error) {
            self::assertSame(1500, $first->atk);
        }
    }

    private static function definition(
        string $id = 'monster-1',
        string $name = 'Monstro fictício',
        string $text = 'Texto fictício.',
        MonsterAttribute $attribute = MonsterAttribute::DARK,
        string $monsterType = 'Dragon',
        int $level = 4,
        int $atk = 1500,
        int $def = 1200,
        MonsterCategory $monsterCategory = MonsterCategory::EFFECT,
    ): MonsterCardDefinition {
        return new MonsterCardDefinition(
            $id,
            $name,
            $text,
            $attribute,
            $monsterType,
            $level,
            $atk,
            $def,
            $monsterCategory,
        );
    }
}
