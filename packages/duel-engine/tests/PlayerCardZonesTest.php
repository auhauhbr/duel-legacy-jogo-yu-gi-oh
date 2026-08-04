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
use DuelLegacy\DuelEngine\Zones\PlayerCardZones;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class PlayerCardZonesTest extends TestCase
{
    /** @return iterable<string, array{string, CardLocation, CardLocation}> */
    public static function incorrectPropertyLocations(): iterable
    {
        yield 'mainDeck com mão' => ['mainDeck', CardLocation::MAIN_DECK, CardLocation::HAND];
        yield 'hand com Deck Principal' => ['hand', CardLocation::HAND, CardLocation::MAIN_DECK];
        yield 'graveyard com banida para cima' => ['graveyard', CardLocation::GRAVEYARD, CardLocation::BANISHED_FACE_UP];
        yield 'banishedFaceUp com banida para baixo' => ['banishedFaceUp', CardLocation::BANISHED_FACE_UP, CardLocation::BANISHED_FACE_DOWN];
        yield 'banishedFaceDown com Cemitério' => ['banishedFaceDown', CardLocation::BANISHED_FACE_DOWN, CardLocation::GRAVEYARD];
        yield 'extraDeckFaceDown com Deck Adicional para cima' => ['extraDeckFaceDown', CardLocation::EXTRA_DECK_FACE_DOWN, CardLocation::EXTRA_DECK_FACE_UP];
        yield 'extraDeckFaceUp com Deck Adicional para baixo' => ['extraDeckFaceUp', CardLocation::EXTRA_DECK_FACE_UP, CardLocation::EXTRA_DECK_FACE_DOWN];
    }

    /** @return iterable<string, array{CardLocation, CardLocation}> */
    public static function duplicateLocationPairs(): iterable
    {
        yield 'Deck Principal e mão' => [CardLocation::MAIN_DECK, CardLocation::HAND];
        yield 'mão e Cemitério' => [CardLocation::HAND, CardLocation::GRAVEYARD];
        yield 'Cemitério e banidas para cima' => [CardLocation::GRAVEYARD, CardLocation::BANISHED_FACE_UP];
        yield 'banidas para cima e para baixo' => [CardLocation::BANISHED_FACE_UP, CardLocation::BANISHED_FACE_DOWN];
        yield 'Deck Adicional para baixo e para cima' => [CardLocation::EXTRA_DECK_FACE_DOWN, CardLocation::EXTRA_DECK_FACE_UP];
        yield 'Deck Principal e Deck Adicional' => [CardLocation::MAIN_DECK, CardLocation::EXTRA_DECK_FACE_DOWN];
    }

    /** @return iterable<string, array{CardLocation}> */
    public static function representedLocations(): iterable
    {
        yield 'Deck Principal' => [CardLocation::MAIN_DECK];
        yield 'mão' => [CardLocation::HAND];
        yield 'Cemitério' => [CardLocation::GRAVEYARD];
        yield 'banidas para cima' => [CardLocation::BANISHED_FACE_UP];
        yield 'banidas para baixo' => [CardLocation::BANISHED_FACE_DOWN];
        yield 'Deck Adicional para baixo' => [CardLocation::EXTRA_DECK_FACE_DOWN];
        yield 'Deck Adicional para cima' => [CardLocation::EXTRA_DECK_FACE_UP];
    }

    /** @return iterable<string, array{CardLocation}> */
    public static function fieldLocations(): iterable
    {
        yield 'Zona de Monstro' => [CardLocation::MONSTER_ZONE];
        yield 'Zona de Magia e Armadilha' => [CardLocation::SPELL_TRAP_ZONE];
        yield 'Field Zone' => [CardLocation::FIELD_ZONE];
    }

    public function test_constructs_with_all_zones_empty(): void
    {
        $zones = self::emptyZones();

        self::assertSame(0, $zones->count());
        self::assertSame([], array_column($zones->toArray()['mainDeck']['cards'], 'id'));
        self::assertCount(7, $zones->zones());
    }

    public function test_constructs_with_one_card_in_every_zone_and_preserves_all_references(): void
    {
        $definition = self::monsterDefinition();
        $cardsByLocation = [];
        $expectedCards = [];
        foreach (self::locations() as $index => $location) {
            $card = self::instance("instance-{$index}", $definition);
            $cardsByLocation[$location->value] = [$card];
            $expectedCards[] = $card;
        }
        $zones = self::zonesWithCards($cardsByLocation);

        foreach ($zones->zones() as $index => $zone) {
            self::assertSame($zone, $zones->get(self::locations()[$index]));
            self::assertSame($expectedCards[$index], $zone->cards()[0]);
            self::assertSame($definition, $zone->cards()[0]->definition);
        }
    }

    public function test_constructs_with_multiple_monster_spell_and_trap_cards_in_every_zone(): void
    {
        $cardsByLocation = [];
        foreach (self::locations() as $index => $location) {
            $cardsByLocation[$location->value] = [
                self::instance("monster-{$index}", self::monsterDefinition()),
                self::instance("spell-{$index}", self::spellDefinition()),
                self::instance("trap-{$index}", self::trapDefinition()),
            ];
        }
        $zones = self::zonesWithCards($cardsByLocation);

        self::assertSame(21, $zones->count());
        foreach ($zones->zones() as $zone) {
            self::assertSame(['MONSTER', 'SPELL', 'TRAP'], array_column(
                array_column($zone->toArray()['cards'], 'definition'),
                'kind',
            ));
        }
    }

    public function test_accepts_the_same_definition_in_different_zones_with_distinct_ids(): void
    {
        $definition = self::spellDefinition();
        $main = self::instance('main-001', $definition);
        $hand = self::instance('hand-001', $definition);
        $zones = self::zonesWithCards([
            CardLocation::MAIN_DECK->value => [$main],
            CardLocation::HAND->value => [$hand],
        ]);

        self::assertSame($main, $zones->mainDeck->cards()[0]);
        self::assertSame($hand, $zones->hand->cards()[0]);
        self::assertSame($definition, $zones->mainDeck->cards()[0]->definition);
        self::assertSame($definition, $zones->hand->cards()[0]->definition);
    }

    #[DataProvider('incorrectPropertyLocations')]
    public function test_rejects_an_incorrect_location_for_each_property(
        string $property,
        CardLocation $expected,
        CardLocation $received,
    ): void {
        $arguments = self::emptyZoneArguments();
        $arguments[$property] = new OrderedCardZone($received);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "A propriedade {$property} exige a localização {$expected->value}; recebida {$received->value}.",
        );

        new PlayerCardZones(...$arguments);
    }

    #[DataProvider('duplicateLocationPairs')]
    public function test_rejects_a_duplicate_id_between_zones_in_fixed_order(
        CardLocation $first,
        CardLocation $second,
    ): void {
        $definition = self::trapDefinition();
        $zones = self::zonesWithCardsArguments([
            $first->value => [self::instance('duplicated-id', $definition)],
            $second->value => [self::instance('duplicated-id', $definition)],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "CardInstanceId duplicado entre {$first->value} e {$second->value}: duplicated-id.",
        );

        new PlayerCardZones(...$zones);
    }

    public function test_rejects_a_duplicate_id_in_non_initial_positions(): void
    {
        $definition = self::monsterDefinition();
        $arguments = self::zonesWithCardsArguments([
            CardLocation::MAIN_DECK->value => [
                self::instance('main-first', $definition),
                self::instance('duplicate-later', $definition),
            ],
            CardLocation::EXTRA_DECK_FACE_UP->value => [
                self::instance('extra-first', $definition),
                self::instance('duplicate-later', $definition),
            ],
        ]);

        $this->expectExceptionMessage(
            'CardInstanceId duplicado entre MAIN_DECK e EXTRA_DECK_FACE_UP: duplicate-later.',
        );

        new PlayerCardZones(...$arguments);
    }

    public function test_three_zone_duplicate_reports_the_first_and_second_locations(): void
    {
        $definition = self::spellDefinition();
        $arguments = self::zonesWithCardsArguments([
            CardLocation::MAIN_DECK->value => [self::instance('triple-id', $definition)],
            CardLocation::HAND->value => [self::instance('triple-id', $definition)],
            CardLocation::GRAVEYARD->value => [self::instance('triple-id', $definition)],
        ]);

        $this->expectExceptionMessage('CardInstanceId duplicado entre MAIN_DECK e HAND: triple-id.');

        new PlayerCardZones(...$arguments);
    }

    public function test_duplicate_comparison_is_case_sensitive_and_does_not_normalize_spaces(): void
    {
        $definition = self::monsterDefinition();
        $zones = self::zonesWithCards([
            CardLocation::MAIN_DECK->value => [self::instance('same-id', $definition)],
            CardLocation::HAND->value => [self::instance('SAME-ID', $definition)],
            CardLocation::GRAVEYARD->value => [self::instance(' same-id ', $definition)],
        ]);

        self::assertSame(3, $zones->count());
        self::assertTrue($zones->contains(new CardInstanceId('same-id')));
        self::assertTrue($zones->contains(new CardInstanceId('SAME-ID')));
        self::assertTrue($zones->contains(new CardInstanceId(' same-id ')));
        self::assertFalse($zones->contains(new CardInstanceId('Same-Id')));
    }

    public function test_zones_returns_a_defensive_list_in_fixed_structural_order(): void
    {
        $zones = self::emptyZones();
        $returned = $zones->zones();

        self::assertSame(self::locations(), array_map(
            static fn (OrderedCardZone $zone): CardLocation => $zone->location,
            $returned,
        ));
        self::assertSame($zones->mainDeck, $returned[0]);
        self::assertSame($zones->extraDeckFaceUp, $returned[6]);

        $returned[0] = $zones->hand;
        array_pop($returned);

        self::assertCount(7, $zones->zones());
        self::assertSame($zones->mainDeck, $zones->zones()[0]);
        self::assertSame($zones->extraDeckFaceUp, $zones->zones()[6]);
    }

    #[DataProvider('representedLocations')]
    public function test_get_returns_the_exact_zone_for_every_represented_location(CardLocation $location): void
    {
        $zones = self::emptyZones();

        self::assertSame($location, $zones->get($location)->location);
        self::assertSame($zones->get($location), $zones->zones()[self::locationIndex($location)]);
    }

    #[DataProvider('fieldLocations')]
    public function test_get_rejects_every_field_location(CardLocation $location): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "A localização {$location->value} não pertence às zonas de cartas do jogador.",
        );

        self::emptyZones()->get($location);
    }

    #[DataProvider('representedLocations')]
    public function test_contains_and_find_query_every_zone_by_id_value(CardLocation $location): void
    {
        $card = self::instance("{$location->value}-instance", self::spellDefinition());
        $zones = self::zonesWithCards([$location->value => [$card]]);
        $equalId = new CardInstanceId("{$location->value}-instance");

        self::assertNotSame($card->id, $equalId);
        self::assertTrue($zones->contains($equalId));
        self::assertSame($card, $zones->find($equalId));
    }

    public function test_contains_returns_false_and_find_returns_null_for_an_absent_id(): void
    {
        $zones = self::zonesWithCards([
            CardLocation::HAND->value => [self::instance('present', self::trapDefinition())],
        ]);

        self::assertFalse($zones->contains(new CardInstanceId('absent')));
        self::assertNull($zones->find(new CardInstanceId('absent')));
    }

    public function test_count_sums_all_zones_and_returns_zero_when_empty(): void
    {
        $cardsByLocation = [];
        foreach (self::locations() as $index => $location) {
            $cardsByLocation[$location->value] = array_map(
                static fn (int $cardIndex): CardInstance => self::instance(
                    "{$location->value}-{$cardIndex}",
                    self::trapDefinition(),
                ),
                range(0, $index),
            );
        }

        self::assertSame(28, self::zonesWithCards($cardsByLocation)->count());
        self::assertSame(0, self::emptyZones()->count());
    }

    public function test_serialization_has_exact_structure_key_order_and_preserves_card_order(): void
    {
        $arguments = self::zonesWithCardsArguments([
            CardLocation::MAIN_DECK->value => [
                self::instance('top', self::monsterDefinition()),
                self::instance('bottom', self::spellDefinition()),
            ],
            CardLocation::GRAVEYARD->value => [self::instance('grave', self::trapDefinition())],
        ]);
        $zones = new PlayerCardZones(...$arguments);
        $expected = [
            'mainDeck' => $arguments['mainDeck']->toArray(),
            'hand' => $arguments['hand']->toArray(),
            'graveyard' => $arguments['graveyard']->toArray(),
            'banishedFaceUp' => $arguments['banishedFaceUp']->toArray(),
            'banishedFaceDown' => $arguments['banishedFaceDown']->toArray(),
            'extraDeckFaceDown' => $arguments['extraDeckFaceDown']->toArray(),
            'extraDeckFaceUp' => $arguments['extraDeckFaceUp']->toArray(),
        ];

        self::assertSame(array_keys($expected), array_keys($zones->toArray()));
        self::assertSame($expected, $zones->toArray());
        self::assertSame(['top', 'bottom'], array_column($zones->toArray()['mainDeck']['cards'], 'id'));
        self::assertSame($expected, $zones->toArray());
    }

    public function test_serialized_arrays_are_deeply_independent(): void
    {
        $definition = self::monsterDefinition();
        $card = self::instance('instance-001', $definition);
        $zones = self::zonesWithCards([CardLocation::HAND->value => [$card]]);
        $expected = $zones->toArray();
        $first = $zones->toArray();
        $second = $zones->toArray();

        $first['hand']['location'] = 'CHANGED';
        $first['hand']['cards'][0]['id'] = 'changed-instance';
        $first['hand']['cards'][0]['definition']['id'] = 'changed-definition';
        $first['mainDeck']['cards'][] = $card->toArray();

        self::assertSame($expected, $second);
        self::assertSame($expected, $zones->toArray());
        self::assertSame('instance-001', $card->id->value);
        self::assertSame('fictional-dragon', $definition->id);
        self::assertSame($card, $zones->hand->cards()[0]);
        self::assertSame($definition, $zones->hand->cards()[0]->definition);
    }

    public function test_is_final_readonly_has_no_setters_and_properties_cannot_be_replaced(): void
    {
        $zones = self::emptyZones();
        $reflection = new ReflectionClass($zones);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertSame([], array_values(array_filter(
            $reflection->getMethods(),
            static fn (ReflectionMethod $method): bool => str_starts_with($method->getName(), 'set'),
        )));

        foreach (self::propertyNames() as $property) {
            try {
                (new ReflectionProperty($zones, $property))->setValue($zones, $zones->hand);
                self::fail("Propriedade readonly {$property} foi substituída.");
            } catch (\Error) {
                self::assertSame(self::locations(), array_map(
                    static fn (OrderedCardZone $zone): CardLocation => $zone->location,
                    $zones->zones(),
                ));
            }
        }
    }

    public function test_contains_only_the_seven_ordered_card_zones_and_no_legacy_state(): void
    {
        $reflection = new ReflectionClass(PlayerCardZones::class);
        $properties = $reflection->getProperties();

        self::assertSame(self::propertyNames(), array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            $properties,
        ));
        foreach ($properties as $property) {
            self::assertSame(OrderedCardZone::class, (string) $property->getType());
        }
        foreach ([
            'playerId', 'lifePoints', 'normalSummonsUsed', 'duelState', 'rng', 'monsterZones',
            'spellTrapZones', 'fieldZone', 'effects', 'controller', 'owner',
        ] as $forbiddenProperty) {
            self::assertNotContains($forbiddenProperty, self::propertyNames());
        }
    }

    public function test_semantically_equal_inputs_produce_equivalent_serializations_without_cloning(): void
    {
        $definition = self::spellDefinition();
        $card = self::instance('instance-001', $definition);
        $zone = new OrderedCardZone(CardLocation::HAND, [$card]);
        $arguments = self::emptyZoneArguments();
        $arguments['hand'] = $zone;
        $first = new PlayerCardZones(...$arguments);
        $second = self::zonesWithCards([
            CardLocation::HAND->value => [self::instance('instance-001', self::spellDefinition())],
        ]);
        $found = $first->find(new CardInstanceId('instance-001'));

        self::assertSame($zone, $first->hand);
        self::assertSame($card, $found);
        self::assertSame($definition, $found->definition);
        self::assertSame($first->toArray(), $second->toArray());
    }

    private static function locationIndex(CardLocation $location): int
    {
        return match ($location) {
            CardLocation::MAIN_DECK => 0,
            CardLocation::HAND => 1,
            CardLocation::GRAVEYARD => 2,
            CardLocation::BANISHED_FACE_UP => 3,
            CardLocation::BANISHED_FACE_DOWN => 4,
            CardLocation::EXTRA_DECK_FACE_DOWN => 5,
            CardLocation::EXTRA_DECK_FACE_UP => 6,
            default => throw new InvalidArgumentException('Localização não representada no teste.'),
        };
    }

    /** @return list<CardLocation> */
    private static function locations(): array
    {
        return [
            CardLocation::MAIN_DECK,
            CardLocation::HAND,
            CardLocation::GRAVEYARD,
            CardLocation::BANISHED_FACE_UP,
            CardLocation::BANISHED_FACE_DOWN,
            CardLocation::EXTRA_DECK_FACE_DOWN,
            CardLocation::EXTRA_DECK_FACE_UP,
        ];
    }

    /** @return list<string> */
    private static function propertyNames(): array
    {
        return [
            'mainDeck',
            'hand',
            'graveyard',
            'banishedFaceUp',
            'banishedFaceDown',
            'extraDeckFaceDown',
            'extraDeckFaceUp',
        ];
    }

    /** @return array<string, OrderedCardZone> */
    private static function emptyZoneArguments(): array
    {
        return array_combine(
            self::propertyNames(),
            array_map(static fn (CardLocation $location): OrderedCardZone => new OrderedCardZone($location), self::locations()),
        );
    }

    private static function emptyZones(): PlayerCardZones
    {
        return new PlayerCardZones(...self::emptyZoneArguments());
    }

    /**
     * @param  array<string, list<CardInstance>>  $cardsByLocation
     * @return array<string, OrderedCardZone>
     */
    private static function zonesWithCardsArguments(array $cardsByLocation): array
    {
        return array_combine(
            self::propertyNames(),
            array_map(
                static fn (CardLocation $location): OrderedCardZone => new OrderedCardZone(
                    $location,
                    $cardsByLocation[$location->value] ?? [],
                ),
                self::locations(),
            ),
        );
    }

    /** @param array<string, list<CardInstance>> $cardsByLocation */
    private static function zonesWithCards(array $cardsByLocation): PlayerCardZones
    {
        return new PlayerCardZones(...self::zonesWithCardsArguments($cardsByLocation));
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
