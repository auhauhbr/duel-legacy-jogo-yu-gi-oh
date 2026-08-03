<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardKind;
use DuelLegacy\DuelEngine\Cards\SpellCardDefinition;
use DuelLegacy\DuelEngine\Cards\SpellType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpellCardDefinitionTest extends TestCase
{
    /** @return iterable<string, array{SpellType}> */
    public static function spellTypes(): iterable
    {
        foreach (SpellType::cases() as $type) {
            yield $type->value => [$type];
        }
    }

    #[DataProvider('spellTypes')]
    public function test_constructs_every_spell_type_and_reports_spell_kind(SpellType $type): void
    {
        $definition = new SpellCardDefinition('spell-1', 'Magia fictícia', '', $type);

        self::assertSame($type, $definition->spellType);
        self::assertSame(CardKind::SPELL, $definition->kind);
        self::assertSame([
            'id' => 'spell-1',
            'name' => 'Magia fictícia',
            'text' => '',
            'kind' => 'SPELL',
            'spellType' => $type->value,
        ], $definition->toArray());
    }

    public function test_preserves_filled_text_and_rejects_invalid_common_fields(): void
    {
        $definition = new SpellCardDefinition(' spell-1 ', ' Magia fictícia ', ' Texto fictício. ', SpellType::QUICK_PLAY);
        self::assertSame([' spell-1 ', ' Magia fictícia ', ' Texto fictício. '], [$definition->id, $definition->name, $definition->text]);

        foreach ([['', 'Nome', 'id não pode ser vazio.'], ['id', '', 'name não pode ser vazio.']] as [$id, $name, $message]) {
            try {
                new SpellCardDefinition($id, $name, '', SpellType::NORMAL);
                self::fail('Campo comum inválido foi aceito.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function test_is_immutable(): void
    {
        $definition = new SpellCardDefinition('spell-1', 'Magia fictícia', '', SpellType::FIELD);
        self::assertTrue((new \ReflectionClass($definition))->isReadOnly());

        try {
            $property = new \ReflectionProperty($definition, 'name');
            $property->setValue($definition, 'Alterada');
            self::fail('Definição readonly foi alterada.');
        } catch (\Error) {
            self::assertSame('Magia fictícia', $definition->name);
        }
    }
}
