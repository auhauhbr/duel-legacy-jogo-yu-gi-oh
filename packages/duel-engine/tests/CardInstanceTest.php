<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardDefinition;
use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Cards\MonsterAttribute;
use DuelLegacy\DuelEngine\Cards\MonsterCardDefinition;
use DuelLegacy\DuelEngine\Cards\MonsterCategory;
use DuelLegacy\DuelEngine\Cards\SpellCardDefinition;
use DuelLegacy\DuelEngine\Cards\SpellType;
use DuelLegacy\DuelEngine\Cards\TrapCardDefinition;
use DuelLegacy\DuelEngine\Cards\TrapType;
use DuelLegacy\DuelEngine\Identifiers\CardInstanceId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use TypeError;

final class CardInstanceTest extends TestCase
{
    /** @return iterable<string, array{CardDefinition, array<string, int|string>}> */
    public static function cardDefinitions(): iterable
    {
        yield 'Monstro' => [
            new MonsterCardDefinition(
                'fictional-dragon',
                'Dragão Fictício',
                'Texto fictício.',
                MonsterAttribute::DARK,
                'Dragon',
                4,
                1500,
                1200,
                MonsterCategory::EFFECT,
            ),
            [
                'id' => 'fictional-dragon',
                'name' => 'Dragão Fictício',
                'text' => 'Texto fictício.',
                'kind' => 'MONSTER',
                'attribute' => 'DARK',
                'monsterType' => 'Dragon',
                'level' => 4,
                'atk' => 1500,
                'def' => 1200,
                'monsterCategory' => 'EFFECT',
            ],
        ];
        yield 'Magia' => [
            new SpellCardDefinition('fictional-spell', 'Magia Fictícia', '', SpellType::QUICK_PLAY),
            [
                'id' => 'fictional-spell',
                'name' => 'Magia Fictícia',
                'text' => '',
                'kind' => 'SPELL',
                'spellType' => 'QUICK_PLAY',
            ],
        ];
        yield 'Armadilha' => [
            new TrapCardDefinition('fictional-trap', 'Armadilha Fictícia', 'Texto fictício.', TrapType::COUNTER),
            [
                'id' => 'fictional-trap',
                'name' => 'Armadilha Fictícia',
                'text' => 'Texto fictício.',
                'kind' => 'TRAP',
                'trapType' => 'COUNTER',
            ],
        ];
    }

    /** @param array<string, int|string> $serializedDefinition */
    #[DataProvider('cardDefinitions')]
    public function test_constructs_and_serializes_every_definition_type(
        CardDefinition $definition,
        array $serializedDefinition,
    ): void {
        $id = new CardInstanceId(' player-1-card-001 ');
        $instance = new CardInstance($id, $definition);

        self::assertSame($id, $instance->id);
        self::assertSame(' player-1-card-001 ', $instance->id->value);
        self::assertSame($definition, $instance->definition);
        self::assertSame($definition->id, $instance->definition->id);
        self::assertNotSame($instance->id->value, $instance->definition->id);
        self::assertSame([
            'id' => ' player-1-card-001 ',
            'definition' => $serializedDefinition,
        ], $instance->toArray());
        self::assertSame(['id', 'definition'], array_keys($instance->toArray()));
    }

    public function test_multiple_instances_share_the_same_definition_without_cloning_it(): void
    {
        $definition = self::spellDefinition();
        $first = new CardInstance(new CardInstanceId('player-1-card-001'), $definition);
        $second = new CardInstance(new CardInstanceId('player-1-card-002'), $definition);

        self::assertSame($definition, $first->definition);
        self::assertSame($definition, $second->definition);
        self::assertSame('player-1-card-001', $first->id->value);
        self::assertSame('player-1-card-002', $second->id->value);

        $firstData = $first->toArray();
        $secondData = $second->toArray();
        self::assertSame($firstData['definition'], $secondData['definition']);
        $secondData['id'] = $firstData['id'];
        self::assertSame($firstData, $secondData);
    }

    public function test_serialization_is_repeatable_and_returns_independent_arrays(): void
    {
        $definition = self::spellDefinition();
        $definitionSnapshot = $definition->toArray();
        $instance = new CardInstance(new CardInstanceId('player-1-card-001'), $definition);
        $first = $instance->toArray();
        $second = $instance->toArray();

        self::assertSame($first, $second);
        $first['id'] = 'changed-instance';
        $first['definition']['id'] = 'changed-definition';
        $first['definition']['name'] = 'Alterada';

        self::assertSame('player-1-card-001', $instance->id->value);
        self::assertSame('fictional-spell', $instance->definition->id);
        self::assertSame($definitionSnapshot, $definition->toArray());
        self::assertSame($second, $instance->toArray());
    }

    public function test_native_types_reject_a_string_id_and_a_non_definition_object(): void
    {
        $constructor = new ReflectionClass(CardInstance::class);
        $rejections = 0;

        foreach ([
            ['player-1-card-001', self::spellDefinition()],
            [new CardInstanceId('player-1-card-001'), new stdClass],
        ] as $arguments) {
            try {
                $constructor->newInstanceArgs($arguments);
                self::fail('Tipo inválido foi aceito por CardInstance.');
            } catch (TypeError) {
                $rejections++;
            }
        }

        self::assertSame(2, $rejections);
    }

    public function test_invalid_card_instance_id_fails_before_an_instance_can_be_formed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O identificador não pode ser vazio.');

        self::assertInstanceOf(
            CardInstance::class,
            new CardInstance(new CardInstanceId(" \t\n"), self::spellDefinition()),
        );
    }

    public function test_is_readonly_has_no_setters_and_cannot_replace_its_properties(): void
    {
        $instance = new CardInstance(new CardInstanceId('player-1-card-001'), self::spellDefinition());
        $reflection = new ReflectionClass($instance);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertSame([], array_values(array_filter(
            $reflection->getMethods(),
            static fn (\ReflectionMethod $method): bool => str_starts_with($method->getName(), 'set'),
        )));

        foreach (['id', 'definition'] as $propertyName) {
            try {
                $property = new ReflectionProperty($instance, $propertyName);
                $property->setValue($instance, $propertyName === 'id'
                    ? new CardInstanceId('replacement')
                    : new TrapCardDefinition('replacement', 'Substituta', '', TrapType::NORMAL));
                self::fail("Propriedade readonly {$propertyName} foi substituída.");
            } catch (\Error) {
                self::assertSame('player-1-card-001', $instance->id->value);
                self::assertSame('fictional-spell', $instance->definition->id);
            }
        }
    }

    public function test_contains_only_identity_and_definition_without_dynamic_duel_state(): void
    {
        $properties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(CardInstance::class))->getProperties(),
        );

        self::assertSame(['id', 'definition'], $properties);
        foreach (['location', 'position', 'owner', 'controller', 'counters', 'effects'] as $dynamicProperty) {
            self::assertNotContains($dynamicProperty, $properties);
        }
    }

    public function test_equal_ids_can_exist_without_claiming_global_duel_validity(): void
    {
        $definition = self::spellDefinition();
        $first = new CardInstance(new CardInstanceId('shared-id'), $definition);
        $second = new CardInstance(new CardInstanceId('shared-id'), $definition);

        self::assertNotSame($first, $second);
        self::assertSame($first->toArray(), $second->toArray());
    }

    public function test_semantically_equal_inputs_produce_equivalent_data(): void
    {
        $firstDefinition = self::spellDefinition();
        $secondDefinition = self::spellDefinition();
        $first = new CardInstance(new CardInstanceId('player-1-card-001'), $firstDefinition);
        $second = new CardInstance(new CardInstanceId('player-1-card-001'), $secondDefinition);

        self::assertNotSame($firstDefinition, $secondDefinition);
        self::assertSame($first->toArray(), $second->toArray());
    }

    private static function spellDefinition(): SpellCardDefinition
    {
        return new SpellCardDefinition('fictional-spell', 'Magia Fictícia', '', SpellType::NORMAL);
    }
}
