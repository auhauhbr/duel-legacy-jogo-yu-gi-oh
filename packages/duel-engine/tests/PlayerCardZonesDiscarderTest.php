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
use DuelLegacy\DuelEngine\Zones\PlayerCardZonesDiscarder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class PlayerCardZonesDiscarderTest extends TestCase
{
    /** @return iterable<string, array{list<string>, string, list<string>}> */
    public static function handPositions(): iterable
    {
        yield 'primeira carta' => [['A', 'B', 'C', 'D'], 'A', ['B', 'C', 'D']];
        yield 'carta intermediária' => [['A', 'B', 'C', 'D'], 'B', ['A', 'C', 'D']];
        yield 'última carta' => [['A', 'B', 'C', 'D'], 'D', ['A', 'B', 'C']];
        yield 'única carta' => [['A'], 'A', []];
    }

    /** @return iterable<string, array{int}> */
    public static function handSizes(): iterable
    {
        yield 'uma carta' => [1];
        yield 'seis cartas' => [6];
        yield 'mais de seis cartas' => [8];
    }

    /** @return iterable<string, array{CardDefinition}> */
    public static function cardDefinitions(): iterable
    {
        yield 'Monstro' => [self::monsterDefinition()];
        yield 'Magia' => [self::spellDefinition()];
        yield 'Armadilha' => [self::trapDefinition()];
    }

    /** @return iterable<string, array{CardLocation}> */
    public static function nonHandLocations(): iterable
    {
        yield 'Deck Principal' => [CardLocation::MAIN_DECK];
        yield 'Cemitério' => [CardLocation::GRAVEYARD];
        yield 'banidas para cima' => [CardLocation::BANISHED_FACE_UP];
        yield 'banidas para baixo' => [CardLocation::BANISHED_FACE_DOWN];
        yield 'Deck Adicional para baixo' => [CardLocation::EXTRA_DECK_FACE_DOWN];
        yield 'Deck Adicional para cima' => [CardLocation::EXTRA_DECK_FACE_UP];
    }

    public function test_is_a_directly_instantiable_stateless_final_readonly_operation_with_exact_signature(): void
    {
        $discarder = new PlayerCardZonesDiscarder;
        $reflection = new ReflectionClass($discarder);
        $discard = $reflection->getMethod('discard');
        $parameters = $discard->getParameters();
        $returnType = $discard->getReturnType();

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertNull($reflection->getConstructor());
        self::assertSame([], $reflection->getProperties());
        self::assertSame(['discard'], array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
        ));
        self::assertSame([], array_values(array_filter(
            $reflection->getMethods(),
            static fn (ReflectionMethod $method): bool => str_starts_with($method->getName(), 'set'),
        )));
        self::assertCount(2, $parameters);
        self::assertSame(['zones', 'cardId'], array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $parameters,
        ));
        self::assertSame(PlayerCardZones::class, (string) $parameters[0]->getType());
        self::assertSame(CardInstanceId::class, (string) $parameters[1]->getType());
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame(PlayerCardZones::class, $returnType->getName());
        self::assertSame([], $reflection->getStaticProperties());
    }

    public function test_discards_the_only_hand_card_to_index_zero_of_an_empty_graveyard(): void
    {
        $definition = self::spellDefinition();
        $card = self::instance('chosen', $definition);
        $zones = self::zones([CardLocation::HAND->value => [$card]]);

        $result = (new PlayerCardZonesDiscarder)->discard($zones, new CardInstanceId('chosen'));

        self::assertNotSame($zones, $result);
        self::assertTrue($result->hand->isEmpty());
        self::assertSame([], $result->hand->cards());
        self::assertSame(0, $result->hand->count());
        self::assertSame([$card], $result->graveyard->cards());
        self::assertSame($card, $result->graveyard->cards()[0]);
        self::assertSame($definition, $result->graveyard->cards()[0]->definition);
        self::assertSame(1, $result->graveyard->count());
        self::assertSame($zones->count(), $result->count());
    }

    /**
     * @param  list<string>  $handIds
     * @param  list<string>  $remainingIds
     */
    #[DataProvider('handPositions')]
    public function test_removes_only_the_explicit_card_and_preserves_relative_hand_order(
        array $handIds,
        string $discardedId,
        array $remainingIds,
    ): void {
        $cards = self::instances($handIds);
        $cardsById = array_combine($handIds, $cards);
        $zones = self::zones([CardLocation::HAND->value => $cards]);

        $result = (new PlayerCardZonesDiscarder)->discard($zones, new CardInstanceId($discardedId));

        self::assertSame($remainingIds, self::ids($result->hand));
        self::assertSame([$discardedId], self::ids($result->graveyard));
        self::assertFalse($result->hand->contains(new CardInstanceId($discardedId)));
        self::assertSame($cardsById[$discardedId], $result->graveyard->cards()[0]);
        foreach ($remainingIds as $index => $remainingId) {
            self::assertSame($cardsById[$remainingId], $result->hand->cards()[$index]);
        }
        self::assertSame($handIds, self::ids($zones->hand));
        self::assertTrue($zones->graveyard->isEmpty());
    }

    public function test_appends_to_a_filled_graveyard_without_reordering_existing_cards(): void
    {
        $definition = self::trapDefinition();
        $chosen = self::instance('B', $definition);
        $x = self::instance('Z', $definition);
        $y = self::instance('A', $definition);
        $z = self::instance('M', $definition);
        $zones = self::zones([
            CardLocation::HAND->value => [self::instance('left', $definition), $chosen, self::instance('right', $definition)],
            CardLocation::GRAVEYARD->value => [$x, $y, $z],
        ]);

        $result = (new PlayerCardZonesDiscarder)->discard($zones, new CardInstanceId('B'));

        self::assertSame(['left', 'right'], self::ids($result->hand));
        self::assertSame(['Z', 'A', 'M', 'B'], self::ids($result->graveyard));
        self::assertSame($x, $result->graveyard->cards()[0]);
        self::assertSame($y, $result->graveyard->cards()[1]);
        self::assertSame($z, $result->graveyard->cards()[2]);
        self::assertSame($chosen, $result->graveyard->cards()[3]);
        self::assertSame($chosen, $result->graveyard->cards()[$result->graveyard->count() - 1]);
        self::assertSame(['Z', 'A', 'M'], self::ids($zones->graveyard));
    }

    #[DataProvider('handSizes')]
    public function test_discards_exactly_one_card_from_any_hand_size_without_excess_processing(int $handSize): void
    {
        $handIds = array_map(static fn (int $index): string => "hand-{$index}", range(1, $handSize));
        $cards = self::instances($handIds);
        $zones = self::zones([CardLocation::HAND->value => $cards]);
        $discardedIndex = intdiv($handSize, 2);
        $discardedId = $handIds[$discardedIndex];

        $result = (new PlayerCardZonesDiscarder)->discard($zones, new CardInstanceId($discardedId));

        $expectedHand = $handIds;
        array_splice($expectedHand, $discardedIndex, 1);
        self::assertSame($expectedHand, self::ids($result->hand));
        self::assertSame([$discardedId], self::ids($result->graveyard));
        self::assertCount($handSize - 1, $result->hand->cards());
        self::assertSame($zones->count(), $result->count());
    }

    #[DataProvider('cardDefinitions')]
    public function test_discard_behavior_does_not_depend_on_the_card_definition(CardDefinition $definition): void
    {
        $card = self::instance('chosen', $definition);
        $result = (new PlayerCardZonesDiscarder)->discard(
            self::zones([CardLocation::HAND->value => [$card]]),
            new CardInstanceId('chosen'),
        );

        self::assertSame($card, $result->graveyard->cards()[0]);
        self::assertSame($definition, $result->graveyard->cards()[0]->definition);
        self::assertTrue($result->hand->isEmpty());
    }

    public function test_preserves_queries_structure_references_and_the_complete_original_aggregate(): void
    {
        $definition = self::monsterDefinition();
        $main = self::instance('main', $definition);
        $left = self::instance('left', $definition);
        $chosen = self::instance('chosen', $definition);
        $right = self::instance('right', $definition);
        $grave = self::instance('grave', $definition);
        $banishedUp = self::instance('banished-up', $definition);
        $banishedDown = self::instance('banished-down', $definition);
        $extraDown = self::instance('extra-down', $definition);
        $extraUp = self::instance('extra-up', $definition);
        $zones = self::zones([
            CardLocation::MAIN_DECK->value => [$main],
            CardLocation::HAND->value => [$left, $chosen, $right],
            CardLocation::GRAVEYARD->value => [$grave],
            CardLocation::BANISHED_FACE_UP->value => [$banishedUp],
            CardLocation::BANISHED_FACE_DOWN->value => [$banishedDown],
            CardLocation::EXTRA_DECK_FACE_DOWN->value => [$extraDown],
            CardLocation::EXTRA_DECK_FACE_UP->value => [$extraUp],
        ]);
        $before = $zones->toArray();
        $originalZones = $zones->zones();

        $result = (new PlayerCardZonesDiscarder)->discard($zones, new CardInstanceId('chosen'));

        self::assertNotSame($zones, $result);
        self::assertNotSame($zones->get(CardLocation::HAND), $result->get(CardLocation::HAND));
        self::assertNotSame($zones->get(CardLocation::GRAVEYARD), $result->get(CardLocation::GRAVEYARD));
        foreach ([CardLocation::MAIN_DECK, CardLocation::BANISHED_FACE_UP, CardLocation::BANISHED_FACE_DOWN, CardLocation::EXTRA_DECK_FACE_DOWN, CardLocation::EXTRA_DECK_FACE_UP] as $location) {
            self::assertSame($zones->get($location), $result->get($location));
        }
        self::assertSame($left, $result->hand->cards()[0]);
        self::assertSame($right, $result->hand->cards()[1]);
        self::assertSame($grave, $result->graveyard->cards()[0]);
        self::assertSame($chosen, $result->graveyard->cards()[1]);
        self::assertSame($definition, $result->graveyard->cards()[1]->definition);
        self::assertFalse($result->hand->contains(new CardInstanceId('chosen')));
        self::assertTrue($result->graveyard->contains(new CardInstanceId('chosen')));
        self::assertTrue($result->contains(new CardInstanceId('chosen')));
        self::assertSame($chosen, $result->find(new CardInstanceId('chosen')));
        self::assertSame($zones->count(), $result->count());
        self::assertSame(self::locations(), array_map(
            static fn (OrderedCardZone $zone): CardLocation => $zone->location,
            $result->zones(),
        ));
        self::assertSame(['mainDeck', 'hand', 'graveyard', 'banishedFaceUp', 'banishedFaceDown', 'extraDeckFaceDown', 'extraDeckFaceUp'], array_keys($result->toArray()));
        self::assertSame($before, $zones->toArray());
        self::assertSame($originalZones, $zones->zones());
    }

    public function test_uses_the_exact_id_value_without_case_trim_unicode_or_definition_matching(): void
    {
        $definition = self::spellDefinition();
        $plain = self::instance('card-id', $definition);
        $upper = self::instance('CARD-ID', $definition);
        $spaced = self::instance(' card-id ', $definition);
        $composed = self::instance('café', $definition);
        $decomposed = self::instance("cafe\u{0301}", $definition);
        $sameDefinition = self::instance('different-id', $definition);
        $zones = self::zones([
            CardLocation::HAND->value => [$plain, $upper, $spaced, $composed, $decomposed, $sameDefinition],
        ]);

        $result = (new PlayerCardZonesDiscarder)->discard($zones, new CardInstanceId(' card-id '));

        self::assertSame(['card-id', 'CARD-ID', 'café', "cafe\u{0301}", 'different-id'], self::ids($result->hand));
        self::assertSame([' card-id '], self::ids($result->graveyard));
        self::assertSame($spaced, $result->graveyard->find(new CardInstanceId(' card-id ')));
        self::assertNull($result->graveyard->find(new CardInstanceId('card-id')));
        self::assertSame($plain, $result->hand->find(new CardInstanceId('card-id')));
        self::assertSame($upper, $result->hand->find(new CardInstanceId('CARD-ID')));
        self::assertSame($composed, $result->hand->find(new CardInstanceId('café')));
        self::assertSame($decomposed, $result->hand->find(new CardInstanceId("cafe\u{0301}")));
        self::assertSame($sameDefinition, $result->hand->find(new CardInstanceId('different-id')));
    }

    public function test_empty_hand_rejects_the_requested_id_atomically(): void
    {
        $this->assertMissingIdFailsAtomically(self::zones([]), 'missing');
    }

    #[DataProvider('nonHandLocations')]
    public function test_id_outside_the_hand_is_not_found_or_moved(CardLocation $location): void
    {
        $outside = self::instance('outside', self::trapDefinition());
        $zones = self::zones([
            CardLocation::HAND->value => self::instances(['hand-card']),
            $location->value => [$outside],
        ]);

        $this->assertMissingIdFailsAtomically($zones, 'outside');
        self::assertSame($outside, $zones->get($location)->find(new CardInstanceId('outside')));
    }

    public function test_successive_discards_compose_and_keep_intermediate_aggregates_immutable(): void
    {
        $definition = self::monsterDefinition();
        $a = self::instance('A', $definition);
        $b = self::instance('B', $definition);
        $c = self::instance('C', $definition);
        $x = self::instance('X', $definition);
        $initial = self::zones([
            CardLocation::HAND->value => [$a, $b, $c],
            CardLocation::GRAVEYARD->value => [$x],
        ]);
        $discarder = new PlayerCardZonesDiscarder;
        $first = $discarder->discard($initial, new CardInstanceId('B'));
        $second = $discarder->discard($first, new CardInstanceId('A'));
        $third = $discarder->discard($second, new CardInstanceId('C'));

        self::assertSame(['A', 'B', 'C'], self::ids($initial->hand));
        self::assertSame(['X'], self::ids($initial->graveyard));
        self::assertSame(['A', 'C'], self::ids($first->hand));
        self::assertSame(['X', 'B'], self::ids($first->graveyard));
        self::assertSame(['C'], self::ids($second->hand));
        self::assertSame(['X', 'B', 'A'], self::ids($second->graveyard));
        self::assertSame([], self::ids($third->hand));
        self::assertSame(['X', 'B', 'A', 'C'], self::ids($third->graveyard));
        self::assertNotSame($initial, $first);
        self::assertNotSame($first, $second);
        self::assertNotSame($second, $third);
        self::assertSame($b, $first->graveyard->cards()[1]);
        self::assertSame($a, $second->graveyard->cards()[2]);
        self::assertSame($c, $third->graveyard->cards()[3]);

        $this->assertMissingIdFailsAtomically($third, 'missing');
    }

    public function test_equivalent_inputs_repeated_calls_and_missing_id_errors_are_deterministic(): void
    {
        $first = self::zones([
            CardLocation::HAND->value => self::instances(['A', 'B', 'C']),
            CardLocation::GRAVEYARD->value => self::instances(['X']),
        ]);
        $second = self::zones([
            CardLocation::HAND->value => self::instances(['A', 'B', 'C']),
            CardLocation::GRAVEYARD->value => self::instances(['X']),
        ]);
        $before = $first->toArray();
        $discarder = new PlayerCardZonesDiscarder;
        $firstResult = $discarder->discard($first, new CardInstanceId('B'));
        $repeatedResult = $discarder->discard($first, new CardInstanceId('B'));
        $equivalentResult = $discarder->discard($second, new CardInstanceId('B'));

        self::assertSame($firstResult->toArray(), $repeatedResult->toArray());
        self::assertSame($firstResult->toArray(), $equivalentResult->toArray());
        self::assertSame(['A', 'C'], self::ids($firstResult->hand));
        self::assertSame(['X', 'B'], self::ids($firstResult->graveyard));
        self::assertSame($before, $first->toArray());

        $messages = [];
        foreach ([$first, $second] as $zones) {
            try {
                $discarder->discard($zones, new CardInstanceId('missing'));
            } catch (InvalidArgumentException $exception) {
                $messages[] = $exception->getMessage();
            }
        }
        self::assertSame([
            'CardInstanceId missing não foi encontrado na localização de origem HAND.',
            'CardInstanceId missing não foi encontrado na localização de origem HAND.',
        ], $messages);
    }

    public function test_source_reuses_the_mover_and_has_no_legacy_duel_or_infrastructure_dependency(): void
    {
        $reflection = new ReflectionClass(PlayerCardZonesDiscarder::class);
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

    private function assertMissingIdFailsAtomically(PlayerCardZones $zones, string $id): void
    {
        $before = $zones->toArray();
        $zoneReferences = $zones->zones();
        $cardsBefore = array_map(
            static fn (OrderedCardZone $zone): array => $zone->cards(),
            $zoneReferences,
        );

        try {
            (new PlayerCardZonesDiscarder)->discard($zones, new CardInstanceId($id));
            self::fail('Descarte de ID ausente na mão foi aceito.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                "CardInstanceId {$id} não foi encontrado na localização de origem HAND.",
                $exception->getMessage(),
            );
        }

        self::assertSame($before, $zones->toArray());
        self::assertSame($zoneReferences, $zones->zones());
        foreach ($zones->zones() as $zoneIndex => $zone) {
            self::assertSame($zoneReferences[$zoneIndex], $zone);
            self::assertSame($cardsBefore[$zoneIndex], $zone->cards());
            foreach ($zone->cards() as $cardIndex => $card) {
                self::assertSame($cardsBefore[$zoneIndex][$cardIndex], $card);
                self::assertSame($cardsBefore[$zoneIndex][$cardIndex]->definition, $card->definition);
            }
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
