<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardKind;
use DuelLegacy\DuelEngine\Cards\MonsterAttribute;
use DuelLegacy\DuelEngine\Cards\MonsterCategory;
use DuelLegacy\DuelEngine\Cards\SpellType;
use DuelLegacy\DuelEngine\Cards\TrapType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CardDefinitionEnumsTest extends TestCase
{
    /** @return iterable<string, array{list<string>, list<string>}> */
    public static function enumValues(): iterable
    {
        yield 'CardKind' => [
            array_map(static fn (CardKind $case): string => $case->value, CardKind::cases()),
            ['MONSTER', 'SPELL', 'TRAP'],
        ];
        yield 'MonsterAttribute' => [
            array_map(static fn (MonsterAttribute $case): string => $case->value, MonsterAttribute::cases()),
            ['DARK', 'DIVINE', 'EARTH', 'FIRE', 'LIGHT', 'WATER', 'WIND'],
        ];
        yield 'MonsterCategory' => [
            array_map(static fn (MonsterCategory $case): string => $case->value, MonsterCategory::cases()),
            ['NORMAL', 'EFFECT', 'RITUAL', 'FUSION'],
        ];
        yield 'SpellType' => [
            array_map(static fn (SpellType $case): string => $case->value, SpellType::cases()),
            ['NORMAL', 'CONTINUOUS', 'EQUIP', 'FIELD', 'QUICK_PLAY', 'RITUAL'],
        ];
        yield 'TrapType' => [
            array_map(static fn (TrapType $case): string => $case->value, TrapType::cases()),
            ['NORMAL', 'CONTINUOUS', 'COUNTER'],
        ];
    }

    /**
     * @param  list<string>  $actual
     * @param  list<string>  $expected
     */
    #[DataProvider('enumValues')]
    public function test_enums_have_exactly_the_expected_serialized_values(array $actual, array $expected): void
    {
        self::assertSame($expected, $actual);
    }

    public function test_monster_categories_do_not_anticipate_modern_mechanics(): void
    {
        $categories = array_map(static fn (MonsterCategory $case): string => $case->value, MonsterCategory::cases());

        foreach (['SYNCHRO', 'XYZ', 'PENDULUM', 'LINK'] as $modernCategory) {
            self::assertNotContains($modernCategory, $categories);
        }
    }
}
