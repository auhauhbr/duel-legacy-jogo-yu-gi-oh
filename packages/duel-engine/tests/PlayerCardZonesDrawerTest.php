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
use DuelLegacy\DuelEngine\Zones\PlayerCardZonesDrawer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class PlayerCardZonesDrawerTest extends TestCase
{
    /** @return iterable<string, array{CardDefinition}> */
    public static function cardDefinitions(): iterable
    {
        yield 'Monstro' => [self::monsterDefinition()];
        yield 'Magia' => [self::spellDefinition()];
        yield 'Armadilha' => [self::trapDefinition()];
    }

    /** @return iterable<string, array{int}> */
    public static function handSizes(): iterable
    {
        yield 'mão vazia' => [0];
        yield 'uma carta' => [1];
        yield 'várias cartas' => [4];
        yield 'seis cartas' => [6];
        yield 'mais de seis cartas' => [8];
    }

    /** @return iterable<string, array{int}> */
    public static function emptyDeckHandSizes(): iterable
    {
        yield 'mão vazia' => [0];
        yield 'mão preenchida' => [3];
    }

    public function test_is_a_directly_instantiable_stateless_final_readonly_operation_with_exact_signature(): void
    {
        $drawer = new PlayerCardZonesDrawer;
        $reflection = new ReflectionClass($drawer);
        $draw = $reflection->getMethod('draw');
        $returnType = $draw->getReturnType();

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertNull($reflection->getConstructor());
        self::assertSame([], $reflection->getProperties());
        self::assertSame(['draw'], array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
        ));
        self::assertSame([], array_values(array_filter(
            $reflection->getMethods(),
            static fn (ReflectionMethod $method): bool => str_starts_with($method->getName(), 'set'),
        )));
        self::assertCount(1, $draw->getParameters());
        self::assertSame(PlayerCardZones::class, (string) $draw->getParameters()[0]->getType());
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame(PlayerCardZones::class, $returnType->getName());
        self::assertSame([], $reflection->getStaticProperties());
    }

    public function test_draws_the_only_main_deck_card_into_an_empty_hand(): void
    {
        $definition = self::spellDefinition();
        $top = self::instance('top', $definition);
        $zones = self::zones([CardLocation::MAIN_DECK->value => [$top]]);

        $result = (new PlayerCardZonesDrawer)->draw($zones);

        self::assertNotSame($zones, $result);
        self::assertTrue($result->mainDeck->isEmpty());
        self::assertSame([], $result->mainDeck->cards());
        self::assertSame(0, $result->mainDeck->count());
        self::assertSame([$top], $result->hand->cards());
        self::assertSame($top, $result->hand->cards()[0]);
        self::assertSame($definition, $result->hand->cards()[0]->definition);
        self::assertSame(1, $result->count());
        self::assertSame(1, $zones->count());
    }

    public function test_removes_only_index_zero_and_preserves_all_orders_references_and_queries(): void
    {
        $definition = self::spellDefinition();
        $top = self::instance('top', $definition);
        $second = self::instance('second', $definition);
        $third = self::instance('third', $definition);
        $handFirst = self::instance('hand-z', $definition);
        $handSecond = self::instance('hand-A', $definition);
        $graveyard = self::instance('graveyard', $definition);
        $banishedUp = self::instance('banished-up', $definition);
        $banishedDown = self::instance('banished-down', $definition);
        $extraDown = self::instance('extra-down', $definition);
        $extraUp = self::instance('extra-up', $definition);
        $zones = self::zones([
            CardLocation::MAIN_DECK->value => [$top, $second, $third],
            CardLocation::HAND->value => [$handFirst, $handSecond],
            CardLocation::GRAVEYARD->value => [$graveyard],
            CardLocation::BANISHED_FACE_UP->value => [$banishedUp],
            CardLocation::BANISHED_FACE_DOWN->value => [$banishedDown],
            CardLocation::EXTRA_DECK_FACE_DOWN->value => [$extraDown],
            CardLocation::EXTRA_DECK_FACE_UP->value => [$extraUp],
        ]);
        $originalArray = $zones->toArray();
        $originalZones = $zones->zones();
        $result = (new PlayerCardZonesDrawer)->draw($zones);

        self::assertSame(['second', 'third'], self::ids($result->get(CardLocation::MAIN_DECK)));
        self::assertSame(['hand-z', 'hand-A', 'top'], self::ids($result->get(CardLocation::HAND)));
        self::assertSame($second, $result->mainDeck->cards()[0]);
        self::assertSame($third, $result->mainDeck->cards()[1]);
        self::assertSame($handFirst, $result->hand->cards()[0]);
        self::assertSame($handSecond, $result->hand->cards()[1]);
        self::assertSame($top, $result->hand->cards()[2]);
        self::assertFalse($result->mainDeck->contains($top->id));
        self::assertTrue($result->hand->contains($top->id));
        self::assertTrue($result->contains(new CardInstanceId('top')));
        self::assertSame($top, $result->find(new CardInstanceId('top')));
        self::assertSame($zones->count(), $result->count());
        self::assertSame(self::locations(), array_map(
            static fn (OrderedCardZone $zone): CardLocation => $zone->location,
            $result->zones(),
        ));
        self::assertSame(['mainDeck', 'hand', 'graveyard', 'banishedFaceUp', 'banishedFaceDown', 'extraDeckFaceDown', 'extraDeckFaceUp'], array_keys($result->toArray()));
        self::assertNotSame($zones->mainDeck, $result->mainDeck);
        self::assertNotSame($zones->hand, $result->hand);
        foreach (array_slice(self::locations(), 2) as $index => $location) {
            self::assertSame($originalZones[$index + 2], $result->get($location));
        }
        self::assertSame($definition, $result->hand->cards()[2]->definition);
        self::assertSame($originalArray, $zones->toArray());
        self::assertSame(['top', 'second', 'third'], self::ids($zones->mainDeck));
        self::assertSame(['hand-z', 'hand-A'], self::ids($zones->hand));
    }

    #[DataProvider('handSizes')]
    public function test_appends_to_any_hand_size_without_sorting_limit_or_discard(int $handSize): void
    {
        $handIds = array_map(static fn (int $index): string => "hand-{$index}", range(1, $handSize));
        if ($handSize === 0) {
            $handIds = [];
        }
        $hand = self::instances($handIds);
        $top = self::instance('drawn', self::trapDefinition());
        $zones = self::zones([
            CardLocation::MAIN_DECK->value => [$top],
            CardLocation::HAND->value => $hand,
        ]);

        $result = (new PlayerCardZonesDrawer)->draw($zones);

        self::assertSame([...$handIds, 'drawn'], self::ids($result->hand));
        self::assertSame($top, $result->hand->cards()[$handSize]);
        self::assertCount($handSize + 1, $result->hand->cards());
        self::assertSame([], self::ids($result->mainDeck));
        foreach ($hand as $index => $card) {
            self::assertSame($card, $result->hand->cards()[$index]);
        }
    }

    #[DataProvider('cardDefinitions')]
    public function test_draw_behavior_does_not_depend_on_the_card_definition(CardDefinition $definition): void
    {
        $card = self::instance('drawn-card', $definition);
        $result = (new PlayerCardZonesDrawer)->draw(self::zones([
            CardLocation::MAIN_DECK->value => [$card],
        ]));

        self::assertSame($card, $result->hand->cards()[0]);
        self::assertSame($definition, $result->hand->cards()[0]->definition);
        self::assertSame([], $result->mainDeck->cards());
    }

    public function test_successive_draws_compose_and_keep_every_intermediate_aggregate_immutable(): void
    {
        $definition = self::monsterDefinition();
        $a = self::instance('A', $definition);
        $b = self::instance('B', $definition);
        $c = self::instance('C', $definition);
        $x = self::instance('X', $definition);
        $initial = self::zones([
            CardLocation::MAIN_DECK->value => [$a, $b, $c],
            CardLocation::HAND->value => [$x],
        ]);
        $drawer = new PlayerCardZonesDrawer;
        $first = $drawer->draw($initial);
        $second = $drawer->draw($first);
        $third = $drawer->draw($second);

        self::assertSame(['A', 'B', 'C'], self::ids($initial->mainDeck));
        self::assertSame(['X'], self::ids($initial->hand));
        self::assertSame(['B', 'C'], self::ids($first->mainDeck));
        self::assertSame(['X', 'A'], self::ids($first->hand));
        self::assertSame(['C'], self::ids($second->mainDeck));
        self::assertSame(['X', 'A', 'B'], self::ids($second->hand));
        self::assertSame([], self::ids($third->mainDeck));
        self::assertSame(['X', 'A', 'B', 'C'], self::ids($third->hand));
        self::assertNotSame($initial, $first);
        self::assertNotSame($first, $second);
        self::assertNotSame($second, $third);
        self::assertSame($a, $first->hand->cards()[1]);
        self::assertSame($b, $second->hand->cards()[2]);
        self::assertSame($c, $third->hand->cards()[3]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Não é possível comprar uma carta: o Deck Principal está vazio.');
        $drawer->draw($third);
    }

    public function test_index_zero_alone_selects_ids_with_case_spaces_and_shared_definitions(): void
    {
        $definition = self::spellDefinition();
        $spaced = self::instance(' card-id ', $definition);
        $plain = self::instance('card-id', $definition);
        $upper = self::instance('CARD-ID', $definition);
        $sameDefinition = self::instance('different-id', $definition);
        $zones = self::zones([
            CardLocation::MAIN_DECK->value => [$spaced, $plain, $upper, $sameDefinition],
        ]);

        $result = (new PlayerCardZonesDrawer)->draw($zones);

        self::assertSame(['card-id', 'CARD-ID', 'different-id'], self::ids($result->mainDeck));
        self::assertSame([' card-id '], self::ids($result->hand));
        self::assertSame($spaced, $result->hand->cards()[0]);
        self::assertSame($definition, $result->hand->cards()[0]->definition);
        self::assertSame($plain, $result->mainDeck->cards()[0]);
        self::assertSame($upper, $result->mainDeck->cards()[1]);
        self::assertSame($sameDefinition, $result->mainDeck->cards()[2]);
    }

    #[DataProvider('emptyDeckHandSizes')]
    public function test_empty_main_deck_fails_atomically_without_fallback(int $handSize): void
    {
        $handIds = array_map(static fn (int $index): string => "hand-{$index}", range(1, $handSize));
        if ($handSize === 0) {
            $handIds = [];
        }
        $zones = self::zones([
            CardLocation::HAND->value => self::instances($handIds),
            CardLocation::EXTRA_DECK_FACE_DOWN->value => self::instances(['extra-fallback']),
        ]);
        $before = $zones->toArray();
        $zoneReferences = $zones->zones();
        $cardsBefore = array_map(
            static fn (OrderedCardZone $zone): array => $zone->cards(),
            $zoneReferences,
        );

        try {
            (new PlayerCardZonesDrawer)->draw($zones);
            self::fail('Compra com Deck Principal vazio foi aceita.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Não é possível comprar uma carta: o Deck Principal está vazio.', $exception->getMessage());
        }

        self::assertSame($before, $zones->toArray());
        self::assertSame($zoneReferences, $zones->zones());
        foreach ($zones->zones() as $index => $zone) {
            self::assertSame($zoneReferences[$index], $zone);
            self::assertSame($cardsBefore[$index], $zone->cards());
            foreach ($zone->cards() as $cardIndex => $card) {
                self::assertSame($cardsBefore[$index][$cardIndex], $card);
                self::assertSame($cardsBefore[$index][$cardIndex]->definition, $card->definition);
            }
        }
        self::assertSame(['extra-fallback'], self::ids($zones->extraDeckFaceDown));
        self::assertSame([], (new ReflectionClass(PlayerCardZonesDrawer::class))->getStaticProperties());
    }

    public function test_equivalent_inputs_and_repeated_draws_are_deterministic(): void
    {
        $first = self::zones([
            CardLocation::MAIN_DECK->value => self::instances(['A', 'B']),
            CardLocation::HAND->value => self::instances(['X']),
        ]);
        $second = self::zones([
            CardLocation::MAIN_DECK->value => self::instances(['A', 'B']),
            CardLocation::HAND->value => self::instances(['X']),
        ]);
        $firstBefore = $first->toArray();
        $drawer = new PlayerCardZonesDrawer;
        $firstResult = $drawer->draw($first);
        $repeatedResult = $drawer->draw($first);
        $equivalentResult = $drawer->draw($second);

        self::assertSame($firstResult->toArray(), $repeatedResult->toArray());
        self::assertSame($firstResult->toArray(), $equivalentResult->toArray());
        self::assertSame(['B'], self::ids($firstResult->mainDeck));
        self::assertSame(['X', 'A'], self::ids($firstResult->hand));
        self::assertSame($firstBefore, $first->toArray());

        $messages = [];
        foreach ([self::zones([]), self::zones([])] as $emptyZones) {
            try {
                $drawer->draw($emptyZones);
            } catch (InvalidArgumentException $exception) {
                $messages[] = $exception->getMessage();
            }
        }
        self::assertSame([
            'Não é possível comprar uma carta: o Deck Principal está vazio.',
            'Não é possível comprar uma carta: o Deck Principal está vazio.',
        ], $messages);
    }

    public function test_source_has_no_legacy_duel_or_infrastructure_dependency(): void
    {
        $reflection = new ReflectionClass(PlayerCardZonesDrawer::class);
        $source = file_get_contents((string) $reflection->getFileName());

        self::assertIsString($source);
        self::assertStringContainsString('new PlayerCardZonesMover', $source);
        foreach ([
            'DuelPlayerState', 'DuelState', '\\Engine;', 'Engine::', 'Rng', 'Database', 'Repository', 'Http',
            'Laravel', 'Catalog', 'Phase', 'Turn', 'ActivePlayer', 'lifePoints', 'Winner', 'Effect', 'Chain',
            'MONSTER_ZONE', 'SPELL_TRAP_ZONE', 'FIELD_ZONE', 'random', 'time(', 'date(', 'static $',
        ] as $forbiddenDependency) {
            self::assertStringNotContainsString($forbiddenDependency, $source);
        }
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

    /** @param array<string, list<CardInstance>> $cardsByLocation */
    private static function zones(array $cardsByLocation): PlayerCardZones
    {
        $zoneObjects = array_map(
            static fn (CardLocation $location): OrderedCardZone => new OrderedCardZone(
                $location,
                $cardsByLocation[$location->value] ?? [],
            ),
            self::locations(),
        );

        return new PlayerCardZones(...array_combine(self::propertyNames(), $zoneObjects));
    }

    /**
     * @param  list<string>  $ids
     * @return list<CardInstance>
     */
    private static function instances(array $ids): array
    {
        $definition = self::spellDefinition();

        return array_map(
            static fn (string $id): CardInstance => self::instance($id, $definition),
            $ids,
        );
    }

    /** @return list<string> */
    private static function ids(OrderedCardZone $zone): array
    {
        return array_map(static fn (CardInstance $card): string => $card->id->value, $zone->cards());
    }

    private static function instance(string $id, CardDefinition $definition): CardInstance
    {
        return new CardInstance(new CardInstanceId($id), $definition);
    }

    private static function monsterDefinition(): MonsterCardDefinition
    {
        return new MonsterCardDefinition(
            'fictional-monster',
            'Monstro Fictício',
            'Texto fictício.',
            MonsterAttribute::DARK,
            'Inventado',
            4,
            1500,
            1200,
            MonsterCategory::EFFECT,
        );
    }

    private static function spellDefinition(): SpellCardDefinition
    {
        return new SpellCardDefinition('fictional-spell', 'Magia Fictícia', 'Texto fictício.', SpellType::NORMAL);
    }

    private static function trapDefinition(): TrapCardDefinition
    {
        return new TrapCardDefinition('fictional-trap', 'Armadilha Fictícia', 'Texto fictício.', TrapType::NORMAL);
    }
}
