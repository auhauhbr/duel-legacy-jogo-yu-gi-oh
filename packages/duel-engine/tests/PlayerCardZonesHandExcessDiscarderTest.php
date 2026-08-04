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
use DuelLegacy\DuelEngine\Zones\PlayerCardZonesHandExcessDiscarder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use stdClass;

final class PlayerCardZonesHandExcessDiscarderTest extends TestCase
{
    /** @return iterable<string, array{int}> */
    public static function negativeHandLimits(): iterable
    {
        yield 'menos um' => [-1];
        yield 'muito negativo' => [-1_000_000];
    }

    /** @return iterable<string, array{int, int}> */
    public static function noExcessCases(): iterable
    {
        yield 'mão abaixo do limite' => [4, 6];
        yield 'mão exatamente no limite' => [6, 6];
        yield 'mão vazia e limite zero' => [0, 0];
    }

    /** @return iterable<string, array{list<string>, int, list<string>, int}> */
    public static function incorrectSelectionCounts(): iterable
    {
        yield 'excesso zero com um ID' => [['A', 'B'], 2, ['A'], 0];
        yield 'excesso um com lista vazia' => [['A', 'B', 'C'], 2, [], 1];
        yield 'excesso dois com um ID' => [['A', 'B', 'C', 'D'], 2, ['A'], 2];
        yield 'excesso um com dois IDs' => [['A', 'B', 'C'], 2, ['A', 'B'], 1];
        yield 'excesso dois com três IDs' => [['A', 'B', 'C', 'D'], 2, ['A', 'B', 'C'], 2];
    }

    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidSelections(): iterable
    {
        $id = new CardInstanceId('A');

        yield 'array associativo' => [['card' => $id]];
        yield 'índice iniciando em um' => [[1 => $id]];
        yield 'lacuna entre índices' => [[0 => $id, 2 => new CardInstanceId('B')]];
        yield 'elemento string' => [['A']];
        yield 'elemento inteiro' => [[1]];
        yield 'elemento null' => [[null]];
        yield 'elemento CardInstance' => [[self::instance('A', self::spellDefinition())]];
        yield 'mistura com objeto inválido' => [[$id, new stdClass]];
    }

    /** @return iterable<string, array{list<CardInstanceId>, string}> */
    public static function duplicateSelections(): iterable
    {
        $sameObject = new CardInstanceId('A');

        yield 'mesmo objeto repetido' => [[$sameObject, $sameObject], 'A'];
        yield 'duas instâncias com o mesmo valor' => [[new CardInstanceId('A'), new CardInstanceId('A')], 'A'];
        yield 'valor contendo espaços' => [[new CardInstanceId(' A '), new CardInstanceId(' A ')], ' A '];
    }

    /** @return iterable<string, array{?CardLocation}> */
    public static function locationsOutsideHand(): iterable
    {
        yield 'ID totalmente inexistente' => [null];
        yield 'Deck Principal' => [CardLocation::MAIN_DECK];
        yield 'Cemitério' => [CardLocation::GRAVEYARD];
        yield 'banidas para cima' => [CardLocation::BANISHED_FACE_UP];
        yield 'banidas para baixo' => [CardLocation::BANISHED_FACE_DOWN];
        yield 'Deck Adicional para baixo' => [CardLocation::EXTRA_DECK_FACE_DOWN];
        yield 'Deck Adicional para cima' => [CardLocation::EXTRA_DECK_FACE_UP];
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function singleDiscardPositions(): iterable
    {
        yield 'primeira carta' => ['A', ['B', 'C', 'D', 'E', 'F', 'G']];
        yield 'posição intermediária' => ['D', ['A', 'B', 'C', 'E', 'F', 'G']];
        yield 'última carta' => ['G', ['A', 'B', 'C', 'D', 'E', 'F']];
    }

    public function test_is_a_directly_instantiable_stateless_final_readonly_operation_with_exact_signature_and_documented_list(): void
    {
        $operation = new PlayerCardZonesHandExcessDiscarder;
        $reflection = new ReflectionClass($operation);
        $method = $reflection->getMethod('discardExcess');
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();
        $docComment = $method->getDocComment();

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertNull($reflection->getConstructor());
        self::assertSame([], $reflection->getProperties());
        self::assertSame([], $reflection->getStaticProperties());
        self::assertSame(['discardExcess'], array_map(
            static fn (ReflectionMethod $publicMethod): string => $publicMethod->getName(),
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
        ));
        self::assertSame([], array_values(array_filter(
            $reflection->getMethods(),
            static fn (ReflectionMethod $candidate): bool => str_starts_with($candidate->getName(), 'set'),
        )));
        self::assertSame(['zones', 'maximumHandSize', 'selectedCardIds'], array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $parameters,
        ));
        self::assertSame(PlayerCardZones::class, (string) $parameters[0]->getType());
        self::assertSame('int', (string) $parameters[1]->getType());
        self::assertSame('array', (string) $parameters[2]->getType());
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame(PlayerCardZones::class, $returnType->getName());
        self::assertIsString($docComment);
        self::assertStringContainsString('list<CardInstanceId>', $docComment);
    }

    #[DataProvider('noExcessCases')]
    public function test_no_excess_returns_the_exact_same_aggregate_and_all_seven_zones(int $handSize, int $maximumHandSize): void
    {
        $handIds = self::letterIds($handSize);
        $zones = self::zones([
            CardLocation::MAIN_DECK->value => self::instances(['MAIN']),
            CardLocation::HAND->value => self::instances($handIds),
            CardLocation::GRAVEYARD->value => self::instances(['GRAVE']),
            CardLocation::BANISHED_FACE_UP->value => self::instances(['UP']),
            CardLocation::BANISHED_FACE_DOWN->value => self::instances(['DOWN']),
            CardLocation::EXTRA_DECK_FACE_DOWN->value => self::instances(['EXTRA-DOWN']),
            CardLocation::EXTRA_DECK_FACE_UP->value => self::instances(['EXTRA-UP']),
        ]);
        $before = $zones->toArray();
        $zoneReferences = $zones->zones();

        $result = (new PlayerCardZonesHandExcessDiscarder)->discardExcess($zones, $maximumHandSize, []);

        self::assertSame($zones, $result);
        self::assertSame($zoneReferences, $result->zones());
        self::assertSame($before, $result->toArray());
        self::assertSame($handIds, self::ids($result->hand));
        self::assertSame(['GRAVE'], self::ids($result->graveyard));
        self::assertSame($zones->count(), $result->count());
    }

    #[DataProvider('negativeHandLimits')]
    public function test_negative_limit_is_rejected_first_and_atomically(int $maximumHandSize): void
    {
        $zones = self::zones([CardLocation::HAND->value => self::instances(['A', 'B'])]);

        $this->assertFailureIsAtomic(
            $zones,
            static fn (): PlayerCardZones => (new PlayerCardZonesHandExcessDiscarder)->discardExcess(
                $zones,
                $maximumHandSize,
                [new CardInstanceId('A'), new CardInstanceId('A')],
            ),
            "O limite máximo da mão não pode ser negativo: {$maximumHandSize}.",
        );
    }

    /**
     * @param  list<string>  $handIds
     * @param  list<string>  $selectedIds
     */
    #[DataProvider('incorrectSelectionCounts')]
    public function test_requires_exactly_the_current_excess(
        array $handIds,
        int $maximumHandSize,
        array $selectedIds,
        int $expectedCount,
    ): void {
        $zones = self::zones([CardLocation::HAND->value => self::instances($handIds)]);
        $selection = self::idsAsValueObjects($selectedIds);
        $actualCount = count($selection);

        $this->assertFailureIsAtomic(
            $zones,
            static fn (): PlayerCardZones => (new PlayerCardZonesHandExcessDiscarder)->discardExcess(
                $zones,
                $maximumHandSize,
                $selection,
            ),
            "A quantidade de cartas selecionadas para descarte deve ser {$expectedCount}; recebida: {$actualCount}.",
        );
    }

    /** @param array<array-key, mixed> $selection */
    #[DataProvider('invalidSelections')]
    public function test_rejects_invalid_php_lists_and_element_types_atomically(array $selection): void
    {
        $zones = self::zones([CardLocation::HAND->value => self::instances(['A', 'B'])]);

        $this->assertFailureIsAtomic(
            $zones,
            static fn (): PlayerCardZones => self::discardWithUncheckedSelection($zones, 1, $selection),
            'Os IDs selecionados para descarte devem formar uma lista de CardInstanceId.',
        );
    }

    /** @param list<CardInstanceId> $selection */
    #[DataProvider('duplicateSelections')]
    public function test_rejects_duplicate_ids_by_their_exact_value_atomically(array $selection, string $duplicateId): void
    {
        $zones = self::zones([
            CardLocation::HAND->value => self::instances([$duplicateId, 'OTHER', 'THIRD']),
        ]);

        $this->assertFailureIsAtomic(
            $zones,
            static fn (): PlayerCardZones => (new PlayerCardZonesHandExcessDiscarder)->discardExcess($zones, 1, $selection),
            "CardInstanceId duplicado na seleção de descarte: {$duplicateId}.",
        );
    }

    public function test_case_and_spaces_remain_distinct_and_safe_as_selection_keys(): void
    {
        $ids = ['CARD', 'card', ' CARD', 'CARD ', '01'];
        $zones = self::zones([CardLocation::HAND->value => self::instances($ids)]);

        $result = (new PlayerCardZonesHandExcessDiscarder)->discardExcess(
            $zones,
            0,
            self::idsAsValueObjects(['CARD ', 'card', '01', 'CARD', ' CARD']),
        );

        self::assertSame([], self::ids($result->hand));
        self::assertSame($ids, self::ids($result->graveyard));
    }

    #[DataProvider('locationsOutsideHand')]
    public function test_selected_id_must_exist_only_in_hand_and_never_uses_a_global_fallback(?CardLocation $location): void
    {
        $cardsByLocation = [CardLocation::HAND->value => self::instances(['A', 'B'])];
        if ($location !== null) {
            $cardsByLocation[$location->value] = self::instances(['OUTSIDE']);
        }
        $zones = self::zones($cardsByLocation);

        $this->assertFailureIsAtomic(
            $zones,
            static fn (): PlayerCardZones => (new PlayerCardZonesHandExcessDiscarder)->discardExcess(
                $zones,
                1,
                [new CardInstanceId('OUTSIDE')],
            ),
            'CardInstanceId OUTSIDE não foi encontrado na localização de origem HAND.',
        );

        if ($location !== null) {
            self::assertTrue($zones->get($location)->contains(new CardInstanceId('OUTSIDE')));
        }
    }

    /** @param list<string> $expectedHand */
    #[DataProvider('singleDiscardPositions')]
    public function test_discards_one_excess_from_any_hand_position_and_appends_it_to_the_graveyard(
        string $selectedId,
        array $expectedHand,
    ): void {
        $zones = self::zones([
            CardLocation::HAND->value => self::instances(['A', 'B', 'C', 'D', 'E', 'F', 'G']),
            CardLocation::GRAVEYARD->value => self::instances(['X', 'Y']),
        ]);

        $result = (new PlayerCardZonesHandExcessDiscarder)->discardExcess(
            $zones,
            6,
            [new CardInstanceId($selectedId)],
        );

        self::assertSame($expectedHand, self::ids($result->hand));
        self::assertSame(['X', 'Y', $selectedId], self::ids($result->graveyard));
        self::assertSame(6, $result->hand->count());
        self::assertSame($zones->count(), $result->count());
    }

    public function test_multiple_discards_follow_the_original_hand_order_not_selection_order(): void
    {
        $hand = self::instances(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);
        $graveyard = self::instances(['X', 'Y']);
        $zones = self::zones([
            CardLocation::HAND->value => $hand,
            CardLocation::GRAVEYARD->value => $graveyard,
        ]);

        $result = (new PlayerCardZonesHandExcessDiscarder)->discardExcess(
            $zones,
            6,
            self::idsAsValueObjects(['H', 'C']),
        );

        self::assertSame(['A', 'B', 'D', 'E', 'F', 'G'], self::ids($result->hand));
        self::assertSame(['X', 'Y', 'C', 'H'], self::ids($result->graveyard));
        self::assertSame($hand[2], $result->graveyard->cards()[2]);
        self::assertSame($hand[7], $result->graveyard->cards()[3]);
        self::assertSame($graveyard[0], $result->graveyard->cards()[0]);
        self::assertSame($graveyard[1], $result->graveyard->cards()[1]);
        self::assertSame(6, $result->hand->count());
        self::assertSame($zones->count(), $result->count());
    }

    public function test_ten_card_hand_discards_four_cards_in_original_order(): void
    {
        $zones = self::zones([
            CardLocation::HAND->value => self::instances(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']),
            CardLocation::GRAVEYARD->value => self::instances(['X']),
        ]);

        $result = (new PlayerCardZonesHandExcessDiscarder)->discardExcess(
            $zones,
            6,
            self::idsAsValueObjects(['J', 'F', 'B', 'A']),
        );

        self::assertSame(['C', 'D', 'E', 'G', 'H', 'I'], self::ids($result->hand));
        self::assertSame(['X', 'A', 'B', 'F', 'J'], self::ids($result->graveyard));
        self::assertSame(6, $result->hand->count());
    }

    public function test_equivalent_selections_in_different_orders_produce_the_same_serialization(): void
    {
        $first = self::zones([
            CardLocation::HAND->value => self::instances(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']),
            CardLocation::GRAVEYARD->value => self::instances(['X']),
        ]);
        $second = self::zones([
            CardLocation::HAND->value => self::instances(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']),
            CardLocation::GRAVEYARD->value => self::instances(['X']),
        ]);
        $operation = new PlayerCardZonesHandExcessDiscarder;

        $firstResult = $operation->discardExcess($first, 6, self::idsAsValueObjects(['H', 'C']));
        $secondResult = $operation->discardExcess($second, 6, self::idsAsValueObjects(['C', 'H']));

        self::assertSame($firstResult->toArray(), $secondResult->toArray());
        self::assertSame(['A', 'B', 'D', 'E', 'F', 'G'], self::ids($firstResult->hand));
        self::assertSame(['X', 'C', 'H'], self::ids($firstResult->graveyard));
        self::assertSame($first->toArray(), $second->toArray());
    }

    public function test_limit_zero_discards_every_card_of_every_definition_type_in_original_order(): void
    {
        $monster = self::instance('MONSTER', self::monsterDefinition());
        $spell = self::instance('SPELL', self::spellDefinition());
        $trap = self::instance('TRAP', self::trapDefinition());
        $grave = self::instance('GRAVE', self::spellDefinition());
        $zones = self::zones([
            CardLocation::HAND->value => [$monster, $spell, $trap],
            CardLocation::GRAVEYARD->value => [$grave],
        ]);

        $result = (new PlayerCardZonesHandExcessDiscarder)->discardExcess(
            $zones,
            0,
            self::idsAsValueObjects(['TRAP', 'MONSTER', 'SPELL']),
        );

        self::assertTrue($result->hand->isEmpty());
        self::assertSame([$grave, $monster, $spell, $trap], $result->graveyard->cards());
        self::assertSame($monster->definition, $result->graveyard->cards()[1]->definition);
        self::assertSame($spell->definition, $result->graveyard->cards()[2]->definition);
        self::assertSame($trap->definition, $result->graveyard->cards()[3]->definition);
    }

    public function test_valid_discard_preserves_queries_structure_sharing_references_and_original_aggregate(): void
    {
        $definition = self::monsterDefinition();
        $main = self::instance('MAIN', $definition);
        $hand = array_map(static fn (string $id): CardInstance => self::instance($id, $definition), ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);
        $grave = self::instance('GRAVE', $definition);
        $up = self::instance('UP', $definition);
        $down = self::instance('DOWN', $definition);
        $extraDown = self::instance('EXTRA-DOWN', $definition);
        $extraUp = self::instance('EXTRA-UP', $definition);
        $zones = self::zones([
            CardLocation::MAIN_DECK->value => [$main],
            CardLocation::HAND->value => $hand,
            CardLocation::GRAVEYARD->value => [$grave],
            CardLocation::BANISHED_FACE_UP->value => [$up],
            CardLocation::BANISHED_FACE_DOWN->value => [$down],
            CardLocation::EXTRA_DECK_FACE_DOWN->value => [$extraDown],
            CardLocation::EXTRA_DECK_FACE_UP->value => [$extraUp],
        ]);
        $before = $zones->toArray();
        $originalZoneReferences = $zones->zones();

        $result = (new PlayerCardZonesHandExcessDiscarder)->discardExcess(
            $zones,
            6,
            self::idsAsValueObjects(['H', 'C']),
        );

        self::assertNotSame($zones, $result);
        self::assertNotSame($zones->hand, $result->hand);
        self::assertNotSame($zones->graveyard, $result->graveyard);
        foreach ([CardLocation::MAIN_DECK, CardLocation::BANISHED_FACE_UP, CardLocation::BANISHED_FACE_DOWN, CardLocation::EXTRA_DECK_FACE_DOWN, CardLocation::EXTRA_DECK_FACE_UP] as $location) {
            self::assertSame($zones->get($location), $result->get($location));
        }
        self::assertSame($hand[2], $result->find(new CardInstanceId('C')));
        self::assertSame($hand[7], $result->find(new CardInstanceId('H')));
        self::assertTrue($result->get(CardLocation::GRAVEYARD)->contains(new CardInstanceId('C')));
        self::assertFalse($result->get(CardLocation::HAND)->contains(new CardInstanceId('C')));
        self::assertSame($definition, $result->find(new CardInstanceId('C'))->definition);
        self::assertSame($zones->count(), $result->count());
        self::assertSame(self::locations(), array_map(
            static fn (OrderedCardZone $zone): CardLocation => $zone->location,
            $result->zones(),
        ));
        self::assertSame(['mainDeck', 'hand', 'graveyard', 'banishedFaceUp', 'banishedFaceDown', 'extraDeckFaceDown', 'extraDeckFaceUp'], array_keys($result->toArray()));
        self::assertSame($before, $zones->toArray());
        self::assertSame($originalZoneReferences, $zones->zones());
        self::assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], self::ids($zones->hand));
        self::assertSame(['GRAVE'], self::ids($zones->graveyard));
    }

    public function test_repeated_equivalent_successes_and_failures_are_deterministic(): void
    {
        $zones = self::zones([CardLocation::HAND->value => self::instances(['A', 'B', 'C'])]);
        $operation = new PlayerCardZonesHandExcessDiscarder;
        $first = $operation->discardExcess($zones, 2, [new CardInstanceId('B')]);
        $second = $operation->discardExcess($zones, 2, [new CardInstanceId('B')]);

        self::assertSame($first->toArray(), $second->toArray());
        self::assertSame(['A', 'C'], self::ids($first->hand));
        self::assertSame(['B'], self::ids($first->graveyard));

        $messages = [];
        foreach ([self::zones([CardLocation::HAND->value => self::instances(['A'])]), self::zones([CardLocation::HAND->value => self::instances(['A'])])] as $invalidZones) {
            try {
                $operation->discardExcess($invalidZones, 0, [new CardInstanceId('MISSING')]);
            } catch (InvalidArgumentException $exception) {
                $messages[] = $exception->getMessage();
            }
        }
        self::assertSame([
            'CardInstanceId MISSING não foi encontrado na localização de origem HAND.',
            'CardInstanceId MISSING não foi encontrado na localização de origem HAND.',
        ], $messages);
    }

    public function test_source_reuses_only_the_typed_unit_discard_and_has_no_legacy_or_infrastructure_dependency(): void
    {
        $reflection = new ReflectionClass(PlayerCardZonesHandExcessDiscarder::class);
        $source = file_get_contents((string) $reflection->getFileName());

        self::assertIsString($source);
        self::assertStringContainsString('new PlayerCardZonesDiscarder', $source);
        self::assertStringContainsString('$discarder->discard(', $source);
        self::assertStringNotContainsString('new PlayerCardZonesMover', $source);
        foreach ([
            'DuelPlayerState', 'DuelState', '\\Engine;', 'Engine::', 'RuleProfile', 'RulesProfile', 'Rng',
            'Database', 'Repository', 'Http', 'Laravel', 'Catalog', 'Phase', 'Turn', 'ActivePlayer',
            'lifePoints', 'Winner', 'Effect', 'Chain', 'MONSTER_ZONE', 'SPELL_TRAP_ZONE', 'FIELD_ZONE',
            'random', 'time(', 'date(', 'static $',
        ] as $forbiddenDependency) {
            self::assertStringNotContainsString($forbiddenDependency, $source);
        }
    }

    /** @param callable(): PlayerCardZones $operation */
    private function assertFailureIsAtomic(PlayerCardZones $zones, callable $operation, string $message): void
    {
        $before = $zones->toArray();
        $zoneReferences = $zones->zones();
        $cardsBefore = array_map(
            static fn (OrderedCardZone $zone): array => $zone->cards(),
            $zoneReferences,
        );

        try {
            $operation();
            self::fail('Entrada inválida foi aceita pelo descarte do excesso da mão.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
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
        self::assertSame([], (new ReflectionClass(PlayerCardZonesHandExcessDiscarder::class))->getStaticProperties());
    }

    /**
     * @param  array<array-key, mixed>  $selection
     */
    private static function discardWithUncheckedSelection(
        PlayerCardZones $zones,
        int $maximumHandSize,
        array $selection,
    ): PlayerCardZones {
        $result = (new ReflectionMethod(PlayerCardZonesHandExcessDiscarder::class, 'discardExcess'))->invoke(
            new PlayerCardZonesHandExcessDiscarder,
            $zones,
            $maximumHandSize,
            $selection,
        );
        self::assertInstanceOf(PlayerCardZones::class, $result);

        return $result;
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

    /**
     * @param  list<string>  $ids
     * @return list<CardInstanceId>
     */
    private static function idsAsValueObjects(array $ids): array
    {
        return array_map(static fn (string $id): CardInstanceId => new CardInstanceId($id), $ids);
    }

    /** @return list<string> */
    private static function ids(OrderedCardZone $zone): array
    {
        return array_map(static fn (CardInstance $card): string => $card->id->value, $zone->cards());
    }

    /** @return list<string> */
    private static function letterIds(int $count): array
    {
        return $count === 0 ? [] : array_slice(range('A', 'Z'), 0, $count);
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
