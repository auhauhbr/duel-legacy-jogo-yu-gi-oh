<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardDefinition;
use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Cards\MonsterAttribute;
use DuelLegacy\DuelEngine\Cards\MonsterCardDefinition;
use DuelLegacy\DuelEngine\Cards\MonsterCategory;
use DuelLegacy\DuelEngine\Cards\SpellCardDefinition;
use DuelLegacy\DuelEngine\Cards\SpellType;
use DuelLegacy\DuelEngine\Cards\TrapCardDefinition;
use DuelLegacy\DuelEngine\Cards\TrapType;
use DuelLegacy\DuelEngine\Identifiers\CardInstanceId;
use DuelLegacy\DuelEngine\Zones\OrderedCardZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use stdClass;

final class OrderedCardZoneTest extends TestCase
{
    /** @return iterable<string, array{CardLocation}> */
    public static function allowedLocations(): iterable
    {
        yield 'Deck Principal' => [CardLocation::MAIN_DECK];
        yield 'mão' => [CardLocation::HAND];
        yield 'Cemitério' => [CardLocation::GRAVEYARD];
        yield 'banidas com a face para cima' => [CardLocation::BANISHED_FACE_UP];
        yield 'banidas com a face para baixo' => [CardLocation::BANISHED_FACE_DOWN];
        yield 'Deck Adicional com a face para baixo' => [CardLocation::EXTRA_DECK_FACE_DOWN];
        yield 'Deck Adicional com a face para cima' => [CardLocation::EXTRA_DECK_FACE_UP];
    }

    /** @return iterable<string, array{CardLocation}> */
    public static function fieldLocations(): iterable
    {
        yield 'Zona de Monstro' => [CardLocation::MONSTER_ZONE];
        yield 'Zona de Magia e Armadilha' => [CardLocation::SPELL_TRAP_ZONE];
        yield 'Field Zone' => [CardLocation::FIELD_ZONE];
    }

    /** @return iterable<string, array{CardDefinition}> */
    public static function cardDefinitions(): iterable
    {
        yield 'Monstro' => [self::monsterDefinition()];
        yield 'Magia' => [self::spellDefinition()];
        yield 'Armadilha' => [self::trapDefinition()];
    }

    #[DataProvider('allowedLocations')]
    public function test_constructs_every_allowed_location_empty(CardLocation $location): void
    {
        $zone = new OrderedCardZone($location);

        self::assertSame($location, $zone->location);
        self::assertSame([], $zone->cards());
        self::assertSame(0, $zone->count());
        self::assertTrue($zone->isEmpty());
        self::assertSame(['location' => $location->value, 'cards' => []], $zone->toArray());
    }

    #[DataProvider('allowedLocations')]
    public function test_constructs_every_allowed_location_with_one_card(CardLocation $location): void
    {
        $card = self::instance('instance-001', self::spellDefinition());
        $zone = new OrderedCardZone($location, [$card]);

        self::assertSame([$card], $zone->cards());
        self::assertSame($card, $zone->cards()[0]);
        self::assertSame($card->definition, $zone->cards()[0]->definition);
        self::assertSame(1, $zone->count());
        self::assertFalse($zone->isEmpty());
    }

    #[DataProvider('allowedLocations')]
    public function test_constructs_every_allowed_location_with_multiple_cards(CardLocation $location): void
    {
        $cards = [
            self::instance('instance-003', self::trapDefinition()),
            self::instance('instance-001', self::monsterDefinition()),
            self::instance('instance-002', self::spellDefinition()),
        ];
        $zone = new OrderedCardZone($location, $cards);

        self::assertSame($cards, $zone->cards());
        self::assertSame(
            ['instance-003', 'instance-001', 'instance-002'],
            array_map(static fn (CardInstance $card): string => $card->id->value, $zone->cards()),
        );
        self::assertSame(3, $zone->count());
    }

    #[DataProvider('cardDefinitions')]
    public function test_accepts_and_serializes_every_card_definition_type(CardDefinition $definition): void
    {
        $card = self::instance('instance-001', $definition);
        $zone = new OrderedCardZone(CardLocation::HAND, [$card]);

        self::assertSame($card, $zone->cards()[0]);
        self::assertSame($definition, $zone->cards()[0]->definition);
        self::assertSame($card->toArray(), $zone->toArray()['cards'][0]);
    }

    public function test_accepts_the_same_definition_with_distinct_instance_ids_and_preserves_references(): void
    {
        $definition = self::monsterDefinition();
        $first = self::instance('instance-001', $definition);
        $second = self::instance('instance-002', $definition);
        $zone = new OrderedCardZone(CardLocation::MAIN_DECK, [$first, $second]);

        self::assertSame([$first, $second], $zone->cards());
        self::assertSame($first, $zone->find(new CardInstanceId('instance-001')));
        self::assertSame($second, $zone->find(new CardInstanceId('instance-002')));
        self::assertSame($definition, $zone->cards()[0]->definition);
        self::assertSame($definition, $zone->cards()[1]->definition);
    }

    public function test_preserves_input_order_without_automatic_sorting(): void
    {
        $cards = array_map(
            static fn (string $id): CardInstance => self::instance($id, self::spellDefinition()),
            ['z-card', 'A-card', '10-card', '2-card', ' a-card '],
        );
        $zone = new OrderedCardZone(CardLocation::HAND, $cards);

        self::assertSame($cards, $zone->cards());
        self::assertSame(
            ['z-card', 'A-card', '10-card', '2-card', ' a-card '],
            array_column($zone->toArray()['cards'], 'id'),
        );
    }

    public function test_main_deck_index_zero_remains_the_top_card(): void
    {
        $top = self::instance('top-card', self::monsterDefinition());
        $middle = self::instance('middle-card', self::spellDefinition());
        $bottom = self::instance('bottom-card', self::trapDefinition());
        $zone = new OrderedCardZone(CardLocation::MAIN_DECK, [$top, $middle, $bottom]);

        self::assertSame($top, $zone->cards()[0]);
        self::assertSame('top-card', $zone->toArray()['cards'][0]['id']);
        self::assertSame(['top-card', 'middle-card', 'bottom-card'], array_column($zone->toArray()['cards'], 'id'));
    }

    public function test_queries_by_exact_card_instance_id_value(): void
    {
        $plain = self::instance('instance-001', self::spellDefinition());
        $upper = self::instance('INSTANCE-001', self::spellDefinition());
        $spaced = self::instance(' instance-001 ', self::spellDefinition());
        $zone = new OrderedCardZone(CardLocation::GRAVEYARD, [$plain, $upper, $spaced]);

        self::assertTrue($zone->contains(new CardInstanceId('instance-001')));
        self::assertTrue($zone->contains(new CardInstanceId('INSTANCE-001')));
        self::assertTrue($zone->contains(new CardInstanceId(' instance-001 ')));
        self::assertFalse($zone->contains(new CardInstanceId('Instance-001')));
        self::assertSame($plain, $zone->find(new CardInstanceId('instance-001')));
        self::assertSame($upper, $zone->find(new CardInstanceId('INSTANCE-001')));
        self::assertSame($spaced, $zone->find(new CardInstanceId(' instance-001 ')));
        self::assertNull($zone->find(new CardInstanceId('missing')));
    }

    public function test_serialization_has_exact_structure_key_order_and_card_data(): void
    {
        $monster = self::instance('monster-instance', self::monsterDefinition());
        $spell = self::instance('spell-instance', self::spellDefinition());
        $trap = self::instance('trap-instance', self::trapDefinition());
        $zone = new OrderedCardZone(CardLocation::BANISHED_FACE_UP, [$monster, $spell, $trap]);

        $expected = [
            'location' => 'BANISHED_FACE_UP',
            'cards' => [$monster->toArray(), $spell->toArray(), $trap->toArray()],
        ];

        self::assertSame(['location', 'cards'], array_keys($zone->toArray()));
        self::assertSame($expected, $zone->toArray());
        self::assertSame($expected, $zone->toArray());
        self::assertSame(['MONSTER', 'SPELL', 'TRAP'], array_column(array_column($zone->toArray()['cards'], 'definition'), 'kind'));
    }

    public function test_serialized_arrays_are_deeply_independent(): void
    {
        $definition = self::spellDefinition();
        $card = self::instance('instance-001', $definition);
        $zone = new OrderedCardZone(CardLocation::EXTRA_DECK_FACE_DOWN, [$card]);
        $expected = $zone->toArray();
        $first = $zone->toArray();
        $second = $zone->toArray();

        $first['location'] = 'CHANGED';
        $first['cards'][0]['id'] = 'changed-instance';
        $first['cards'][0]['definition']['id'] = 'changed-definition';
        $first['cards'][0]['definition']['name'] = 'Alterada';

        self::assertSame($expected, $second);
        self::assertSame($expected, $zone->toArray());
        self::assertSame('instance-001', $card->id->value);
        self::assertSame('fictional-spell', $definition->id);
        self::assertSame('Magia Fictícia', $definition->name);
    }

    public function test_repeated_id_is_rejected_even_when_not_consecutive(): void
    {
        $definition = self::spellDefinition();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CardInstanceId duplicado na zona ordenada fora do campo: instance-001.');

        new OrderedCardZone(CardLocation::HAND, [
            self::instance('instance-001', $definition),
            self::instance('instance-002', $definition),
            self::instance('instance-001', $definition),
        ]);
    }

    public function test_consecutive_duplicate_id_has_deterministic_message(): void
    {
        $definition = self::trapDefinition();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CardInstanceId duplicado na zona ordenada fora do campo: repeated-id.');

        new OrderedCardZone(CardLocation::GRAVEYARD, [
            self::instance('repeated-id', $definition),
            self::instance('repeated-id', $definition),
        ]);
    }

    public function test_duplicate_comparison_is_case_sensitive_and_does_not_normalize_spaces(): void
    {
        $definition = self::monsterDefinition();
        $zone = new OrderedCardZone(CardLocation::BANISHED_FACE_DOWN, [
            self::instance('same-id', $definition),
            self::instance('SAME-ID', $definition),
            self::instance(' same-id ', $definition),
        ]);

        self::assertSame(['same-id', 'SAME-ID', ' same-id '], array_column($zone->toArray()['cards'], 'id'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CardInstanceId duplicado na zona ordenada fora do campo:  same-id .');

        new OrderedCardZone(CardLocation::BANISHED_FACE_DOWN, [
            self::instance(' same-id ', $definition),
            self::instance(' same-id ', $definition),
        ]);
    }

    #[DataProvider('fieldLocations')]
    public function test_rejects_every_existing_field_location(CardLocation $location): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "A localização {$location->value} não é aceita por uma zona ordenada fora do campo.",
        );

        new OrderedCardZone($location);
    }

    public function test_rejects_associative_and_gapped_arrays(): void
    {
        $card = self::instance('instance-001', self::spellDefinition());
        $rejections = 0;

        foreach ([['card' => $card], [0 => $card, 2 => $card]] as $invalidCards) {
            try {
                self::constructWithUncheckedCards($invalidCards);
                self::fail('Array que não é lista foi aceito.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('cards deve ser uma lista.', $exception->getMessage());
                $rejections++;
            }
        }

        self::assertSame(2, $rejections);
    }

    public function test_rejects_string_and_arbitrary_object_elements(): void
    {
        $rejections = 0;

        foreach ([['instance-001'], [new stdClass]] as $invalidCards) {
            try {
                self::constructWithUncheckedCards($invalidCards);
                self::fail('Elemento que não é CardInstance foi aceito.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('cards deve conter apenas CardInstance.', $exception->getMessage());
                $rejections++;
            }
        }

        self::assertSame(2, $rejections);
    }

    public function test_original_and_returned_card_arrays_cannot_modify_the_zone(): void
    {
        $first = self::instance('instance-001', self::spellDefinition());
        $second = self::instance('instance-002', self::trapDefinition());
        $input = [$first];
        $zone = new OrderedCardZone(CardLocation::HAND, $input);

        $input[0] = $second;
        $input[] = $second;
        $returned = $zone->cards();
        $returned[0] = $second;
        $returned[] = $second;

        self::assertSame([$first], $zone->cards());
        self::assertSame(1, $zone->count());
        self::assertSame('instance-001', $zone->toArray()['cards'][0]['id']);
        self::assertSame($first, $zone->cards()[0]);
    }

    public function test_is_final_readonly_has_no_setters_and_properties_cannot_be_replaced(): void
    {
        $card = self::instance('instance-001', self::spellDefinition());
        $zone = new OrderedCardZone(CardLocation::HAND, [$card]);
        $reflection = new ReflectionClass($zone);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertSame([], array_values(array_filter(
            $reflection->getMethods(),
            static fn (ReflectionMethod $method): bool => str_starts_with($method->getName(), 'set'),
        )));

        $replacements = [
            'location' => CardLocation::GRAVEYARD,
            'cards' => [self::instance('replacement', self::trapDefinition())],
        ];
        foreach ($replacements as $propertyName => $replacement) {
            try {
                (new ReflectionProperty($zone, $propertyName))->setValue($zone, $replacement);
                self::fail("Propriedade readonly {$propertyName} foi substituída.");
            } catch (\Error) {
                self::assertSame(CardLocation::HAND, $zone->location);
                self::assertSame([$card], $zone->cards());
            }
        }
    }

    public function test_contains_only_location_and_cards_without_field_or_duel_state(): void
    {
        $reflection = new ReflectionClass(OrderedCardZone::class);
        $properties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            $reflection->getProperties(),
        );

        self::assertSame(['location', 'cards'], $properties);
        foreach (['position', 'owner', 'controller', 'effects', 'counters', 'faceUp', 'normalSummons', 'rng', 'duelState'] as $forbiddenProperty) {
            self::assertNotContains($forbiddenProperty, $properties);
        }

        foreach ($reflection->getMethods() as $method) {
            $types = array_filter([
                $method->getReturnType(),
                ...array_map(static fn (\ReflectionParameter $parameter): ?\ReflectionType => $parameter->getType(), $method->getParameters()),
            ]);
            foreach ($types as $type) {
                if ($type instanceof ReflectionNamedType) {
                    self::assertNotSame('DuelLegacy\\DuelEngine\\Duels\\DuelState', $type->getName());
                }
            }
        }
    }

    public function test_semantically_equal_inputs_produce_equivalent_serializations(): void
    {
        $first = new OrderedCardZone(CardLocation::EXTRA_DECK_FACE_UP, [
            self::instance('instance-001', self::spellDefinition()),
        ]);
        $second = new OrderedCardZone(CardLocation::EXTRA_DECK_FACE_UP, [
            self::instance('instance-001', self::spellDefinition()),
        ]);

        self::assertNotSame($first, $second);
        self::assertNotSame($first->cards()[0], $second->cards()[0]);
        self::assertNotSame($first->cards()[0]->definition, $second->cards()[0]->definition);
        self::assertSame($first->toArray(), $second->toArray());
    }

    /** @param array<array-key, mixed> $cards */
    private static function constructWithUncheckedCards(array $cards): OrderedCardZone
    {
        return (new ReflectionClass(OrderedCardZone::class))->newInstance(CardLocation::HAND, $cards);
    }

    private static function instance(string $id, CardDefinition $definition): CardInstance
    {
        return new CardInstance(new CardInstanceId($id), $definition);
    }

    private static function monsterDefinition(): MonsterCardDefinition
    {
        return new MonsterCardDefinition(
            'fictional-dragon',
            'Dragão Fictício',
            'Texto fictício.',
            MonsterAttribute::DARK,
            'Dragon',
            4,
            1500,
            1200,
            MonsterCategory::EFFECT,
        );
    }

    private static function spellDefinition(): SpellCardDefinition
    {
        return new SpellCardDefinition('fictional-spell', 'Magia Fictícia', '', SpellType::NORMAL);
    }

    private static function trapDefinition(): TrapCardDefinition
    {
        return new TrapCardDefinition('fictional-trap', 'Armadilha Fictícia', '', TrapType::NORMAL);
    }
}
