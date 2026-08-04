<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardDefinition;
use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Cards\SpellCardDefinition;
use DuelLegacy\DuelEngine\Cards\SpellType;
use DuelLegacy\DuelEngine\Identifiers\CardInstanceId;
use DuelLegacy\DuelEngine\Zones\OrderedCardZone;
use DuelLegacy\DuelEngine\Zones\PlayerCardZones;
use DuelLegacy\DuelEngine\Zones\PlayerCardZonesMover;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class PlayerCardZonesMoverTest extends TestCase
{
    /** @return iterable<string, array{CardLocation, CardLocation}> */
    public static function validMoves(): iterable
    {
        yield 'Deck Principal para mão' => [CardLocation::MAIN_DECK, CardLocation::HAND];
        yield 'mão para Cemitério' => [CardLocation::HAND, CardLocation::GRAVEYARD];
        yield 'Cemitério para banidas para cima' => [CardLocation::GRAVEYARD, CardLocation::BANISHED_FACE_UP];
        yield 'banidas para cima para banidas para baixo' => [CardLocation::BANISHED_FACE_UP, CardLocation::BANISHED_FACE_DOWN];
        yield 'banidas para baixo para Deck Principal' => [CardLocation::BANISHED_FACE_DOWN, CardLocation::MAIN_DECK];
        yield 'Deck Adicional para baixo para cima' => [CardLocation::EXTRA_DECK_FACE_DOWN, CardLocation::EXTRA_DECK_FACE_UP];
        yield 'Deck Adicional para cima para baixo' => [CardLocation::EXTRA_DECK_FACE_UP, CardLocation::EXTRA_DECK_FACE_DOWN];
        yield 'Deck Principal para Deck Adicional' => [CardLocation::MAIN_DECK, CardLocation::EXTRA_DECK_FACE_DOWN];
        yield 'Deck Adicional para Cemitério' => [CardLocation::EXTRA_DECK_FACE_DOWN, CardLocation::GRAVEYARD];
    }

    /** @return iterable<string, array{list<string>, string, list<string>}> */
    public static function sourceRemovals(): iterable
    {
        yield 'primeira carta' => [['move', 'b', 'c', 'd'], 'move', ['b', 'c', 'd']];
        yield 'carta intermediária' => [['a', 'b', 'move', 'd'], 'move', ['a', 'b', 'd']];
        yield 'última carta' => [['a', 'b', 'c', 'move'], 'move', ['a', 'b', 'c']];
        yield 'única carta' => [['move'], 'move', []];
    }

    /** @return iterable<string, array{list<string>, int, list<string>}> */
    public static function destinationInsertions(): iterable
    {
        yield 'destino vazio' => [[], 0, ['move']];
        yield 'primeiro índice' => [['x', 'y', 'z'], 0, ['move', 'x', 'y', 'z']];
        yield 'índice intermediário' => [['x', 'y', 'z'], 2, ['x', 'y', 'move', 'z']];
        yield 'índice igual à contagem' => [['x', 'y', 'z'], 3, ['x', 'y', 'z', 'move']];
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

    /** @return iterable<string, array{int, int}> */
    public static function invalidDestinationIndices(): iterable
    {
        yield 'menos um' => [-1, 2];
        yield 'muito negativo' => [-999, 2];
        yield 'contagem mais um' => [3, 2];
        yield 'muito maior' => [999, 2];
        yield 'um em destino vazio' => [1, 0];
    }

    /** @return iterable<string, array{CardLocation, CardLocation}> */
    public static function fieldMoves(): iterable
    {
        yield 'Zona de Monstro como origem' => [CardLocation::MONSTER_ZONE, CardLocation::HAND];
        yield 'Zona de Magia e Armadilha como origem' => [CardLocation::SPELL_TRAP_ZONE, CardLocation::HAND];
        yield 'Field Zone como origem' => [CardLocation::FIELD_ZONE, CardLocation::HAND];
        yield 'Zona de Monstro como destino' => [CardLocation::HAND, CardLocation::MONSTER_ZONE];
        yield 'Zona de Magia e Armadilha como destino' => [CardLocation::HAND, CardLocation::SPELL_TRAP_ZONE];
        yield 'Field Zone como destino' => [CardLocation::HAND, CardLocation::FIELD_ZONE];
    }

    public function test_is_a_directly_instantiable_stateless_final_readonly_operation(): void
    {
        $mover = new PlayerCardZonesMover;
        $reflection = new ReflectionClass($mover);
        $move = $reflection->getMethod('move');
        $parameterTypes = array_map(
            static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType(),
            $move->getParameters(),
        );
        $returnType = $move->getReturnType();

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertNull($reflection->getConstructor());
        self::assertSame([], $reflection->getProperties());
        self::assertSame([], array_values(array_filter(
            $reflection->getMethods(),
            static fn (ReflectionMethod $method): bool => str_starts_with($method->getName(), 'set'),
        )));
        self::assertSame([
            PlayerCardZones::class,
            CardInstanceId::class,
            CardLocation::class,
            CardLocation::class,
            'int',
        ], $parameterTypes);
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame(PlayerCardZones::class, $returnType->getName());
    }

    #[DataProvider('validMoves')]
    public function test_moves_between_every_supported_location_and_structurally_shares_unaffected_zones(
        CardLocation $source,
        CardLocation $destination,
    ): void {
        $definition = self::definition();
        $moved = self::instance('move', $definition);
        $sourcePeer = self::instance('source-peer', $definition);
        $before = self::instance('destination-before', $definition);
        $after = self::instance('destination-after', $definition);
        $zones = self::zones([
            $source->value => [$sourcePeer, $moved],
            $destination->value => [$before, $after],
        ]);
        $originalArray = $zones->toArray();
        $originalZoneReferences = $zones->zones();

        $result = (new PlayerCardZonesMover)->move(
            $zones,
            new CardInstanceId('move'),
            $source,
            $destination,
            1,
        );

        self::assertNotSame($zones, $result);
        self::assertNotSame($zones->get($source), $result->get($source));
        self::assertNotSame($zones->get($destination), $result->get($destination));
        self::assertSame(['source-peer'], self::ids($result->get($source)));
        self::assertSame(['destination-before', 'move', 'destination-after'], self::ids($result->get($destination)));
        self::assertSame($moved, $result->get($destination)->cards()[1]);
        self::assertSame($definition, $result->get($destination)->cards()[1]->definition);
        self::assertSame($sourcePeer, $result->get($source)->cards()[0]);
        self::assertSame($before, $result->get($destination)->cards()[0]);
        self::assertSame($after, $result->get($destination)->cards()[2]);
        self::assertFalse($result->get($source)->contains($moved->id));
        self::assertTrue($result->get($destination)->contains(new CardInstanceId('move')));
        self::assertTrue($result->contains(new CardInstanceId('move')));
        self::assertSame($moved, $result->find(new CardInstanceId('move')));
        self::assertSame($zones->count(), $result->count());
        self::assertSame(self::locations(), array_map(
            static fn (OrderedCardZone $zone): CardLocation => $zone->location,
            $result->zones(),
        ));
        self::assertSame($originalArray, $zones->toArray());

        foreach (self::locations() as $index => $location) {
            if ($location === $source || $location === $destination) {
                self::assertNotSame($originalZoneReferences[$index], $result->get($location));
            } else {
                self::assertSame($originalZoneReferences[$index], $result->get($location));
            }
        }
    }

    /**
     * @param  list<string>  $sourceIds
     * @param  list<string>  $expectedIds
     */
    #[DataProvider('sourceRemovals')]
    public function test_removes_exactly_one_card_and_preserves_the_relative_source_order(
        array $sourceIds,
        string $movedId,
        array $expectedIds,
    ): void {
        $zones = self::zones([CardLocation::HAND->value => self::instances($sourceIds)]);
        $sourceBefore = $zones->hand->cards();
        $result = (new PlayerCardZonesMover)->move(
            $zones,
            new CardInstanceId($movedId),
            CardLocation::HAND,
            CardLocation::GRAVEYARD,
            0,
        );

        self::assertSame($expectedIds, self::ids($result->hand));
        self::assertSame($expectedIds === [], $result->hand->isEmpty());
        foreach ($result->hand->cards() as $card) {
            self::assertContains($card, $sourceBefore);
        }
        self::assertSame($sourceIds, self::ids($zones->hand));
    }

    /**
     * @param  list<string>  $destinationIds
     * @param  list<string>  $expectedIds
     */
    #[DataProvider('destinationInsertions')]
    public function test_inserts_at_the_exact_destination_index_and_preserves_existing_order(
        array $destinationIds,
        int $destinationIndex,
        array $expectedIds,
    ): void {
        $moved = self::instance('move', self::definition());
        $destinationCards = self::instances($destinationIds);
        $zones = self::zones([
            CardLocation::HAND->value => [$moved],
            CardLocation::GRAVEYARD->value => $destinationCards,
        ]);

        $result = (new PlayerCardZonesMover)->move(
            $zones,
            new CardInstanceId('move'),
            CardLocation::HAND,
            CardLocation::GRAVEYARD,
            $destinationIndex,
        );

        self::assertSame($expectedIds, self::ids($result->graveyard));
        self::assertSame($moved, $result->graveyard->cards()[$destinationIndex]);
        foreach ($destinationCards as $card) {
            self::assertContains($card, $result->graveyard->cards());
        }
        self::assertSame($destinationIds, self::ids($zones->graveyard));
    }

    public function test_main_deck_index_zero_is_the_top_and_count_is_the_end(): void
    {
        $definition = self::definition();
        $top = self::instance('top', $definition);
        $middle = self::instance('middle', $definition);
        $bottom = self::instance('bottom', $definition);
        $firstMoved = self::instance('first-moved', $definition);
        $lastMoved = self::instance('last-moved', $definition);
        $zones = self::zones([
            CardLocation::MAIN_DECK->value => [$top, $middle, $bottom],
            CardLocation::HAND->value => [$firstMoved, $lastMoved],
        ]);
        $withoutTop = (new PlayerCardZonesMover)->move(
            $zones,
            new CardInstanceId('top'),
            CardLocation::MAIN_DECK,
            CardLocation::GRAVEYARD,
            0,
        );
        $withNewTop = (new PlayerCardZonesMover)->move(
            $zones,
            new CardInstanceId('first-moved'),
            CardLocation::HAND,
            CardLocation::MAIN_DECK,
            0,
        );
        $withNewEnd = (new PlayerCardZonesMover)->move(
            $zones,
            new CardInstanceId('last-moved'),
            CardLocation::HAND,
            CardLocation::MAIN_DECK,
            $zones->mainDeck->count(),
        );

        self::assertSame(['middle', 'bottom'], self::ids($withoutTop->mainDeck));
        self::assertSame(['first-moved', 'top', 'middle', 'bottom'], self::ids($withNewTop->mainDeck));
        self::assertSame($firstMoved, $withNewTop->mainDeck->cards()[0]);
        self::assertSame(['top', 'middle', 'bottom', 'last-moved'], self::ids($withNewEnd->mainDeck));
        self::assertSame($lastMoved, $withNewEnd->mainDeck->cards()[3]);
        self::assertSame(['top', 'middle', 'bottom'], self::ids($zones->mainDeck));
    }

    public function test_matches_ids_exactly_without_case_trim_or_normalization(): void
    {
        $definition = self::definition();
        $plain = self::instance('card-id', $definition);
        $upper = self::instance('CARD-ID', $definition);
        $spaced = self::instance(' card-id ', $definition);
        $unicode = self::instance('café', $definition);
        $decomposed = self::instance("cafe\u{0301}", $definition);
        $zones = self::zones([
            CardLocation::HAND->value => [$plain, $upper, $spaced, $unicode, $decomposed],
        ]);

        $result = (new PlayerCardZonesMover)->move(
            $zones,
            new CardInstanceId(' card-id '),
            CardLocation::HAND,
            CardLocation::GRAVEYARD,
            0,
        );

        self::assertSame(['card-id', 'CARD-ID', 'café', "cafe\u{0301}"], self::ids($result->hand));
        self::assertSame($spaced, $result->graveyard->find(new CardInstanceId(' card-id ')));
        self::assertNull($result->graveyard->find(new CardInstanceId('card-id')));
        self::assertSame($plain, $result->hand->find(new CardInstanceId('card-id')));
        self::assertSame($upper, $result->hand->find(new CardInstanceId('CARD-ID')));
        self::assertSame($unicode, $result->hand->find(new CardInstanceId('café')));
        self::assertSame($decomposed, $result->hand->find(new CardInstanceId("cafe\u{0301}")));
    }

    public function test_rejects_an_absent_id_and_does_not_search_another_zone(): void
    {
        $zones = self::zones([
            CardLocation::MAIN_DECK->value => self::instances(['deck-card']),
            CardLocation::HAND->value => self::instances(['hand-card']),
        ]);

        foreach (['absent', 'hand-card'] as $id) {
            self::assertFailureIsAtomic(
                $zones,
                new CardInstanceId($id),
                CardLocation::MAIN_DECK,
                CardLocation::GRAVEYARD,
                0,
                "CardInstanceId {$id} não foi encontrado na localização de origem MAIN_DECK.",
            );
        }
    }

    #[DataProvider('representedLocations')]
    public function test_rejects_equal_source_and_destination_without_a_no_op(CardLocation $location): void
    {
        $zones = self::zones([$location->value => self::instances(['move'])]);

        self::assertFailureIsAtomic(
            $zones,
            new CardInstanceId('move'),
            $location,
            $location,
            0,
            "A origem e o destino da movimentação devem ser diferentes: {$location->value}.",
        );
    }

    #[DataProvider('invalidDestinationIndices')]
    public function test_rejects_an_invalid_destination_index_against_the_original_count(
        int $destinationIndex,
        int $destinationCount,
    ): void {
        $destinationIds = array_map(
            static fn (int $index): string => "destination-{$index}",
            range(1, $destinationCount),
        );
        if ($destinationCount === 0) {
            $destinationIds = [];
        }
        $zones = self::zones([
            CardLocation::HAND->value => self::instances(['move']),
            CardLocation::GRAVEYARD->value => self::instances($destinationIds),
        ]);

        self::assertFailureIsAtomic(
            $zones,
            new CardInstanceId('move'),
            CardLocation::HAND,
            CardLocation::GRAVEYARD,
            $destinationIndex,
            "Índice de destino inválido para GRAVEYARD: {$destinationIndex}; intervalo permitido de 0 a {$destinationCount}.",
        );
    }

    #[DataProvider('fieldMoves')]
    public function test_rejects_field_locations_with_the_player_card_zones_message(
        CardLocation $source,
        CardLocation $destination,
    ): void {
        $zones = self::zones([CardLocation::HAND->value => self::instances(['move'])]);
        $fieldLocation = in_array($source, self::locations(), true) ? $destination : $source;
        $before = $zones->toArray();

        try {
            (new PlayerCardZonesMover)->move(
                $zones,
                new CardInstanceId('move'),
                $source,
                $destination,
                0,
            );
            self::fail('Localização de campo foi aceita.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                "A localização {$fieldLocation->value} não pertence às zonas de cartas do jogador.",
                $exception->getMessage(),
            );
        }

        self::assertSame($before, $zones->toArray());
    }

    public function test_equivalent_moves_are_deterministic(): void
    {
        $first = self::zones([
            CardLocation::MAIN_DECK->value => self::instances(['top', 'move', 'bottom']),
            CardLocation::HAND->value => self::instances(['left', 'right']),
        ]);
        $second = self::zones([
            CardLocation::MAIN_DECK->value => self::instances(['top', 'move', 'bottom']),
            CardLocation::HAND->value => self::instances(['left', 'right']),
        ]);
        $mover = new PlayerCardZonesMover;

        $firstResult = $mover->move($first, new CardInstanceId('move'), CardLocation::MAIN_DECK, CardLocation::HAND, 1);
        $secondResult = $mover->move($second, new CardInstanceId('move'), CardLocation::MAIN_DECK, CardLocation::HAND, 1);

        self::assertSame($firstResult->toArray(), $secondResult->toArray());
        self::assertSame(self::ids($firstResult->mainDeck), self::ids($secondResult->mainDeck));
        self::assertSame(self::ids($firstResult->hand), self::ids($secondResult->hand));

        $messages = [];
        foreach ([$first, $second] as $zones) {
            try {
                $mover->move($zones, new CardInstanceId('missing'), CardLocation::MAIN_DECK, CardLocation::HAND, 0);
            } catch (InvalidArgumentException $exception) {
                $messages[] = $exception->getMessage();
            }
        }
        self::assertSame([
            'CardInstanceId missing não foi encontrado na localização de origem MAIN_DECK.',
            'CardInstanceId missing não foi encontrado na localização de origem MAIN_DECK.',
        ], $messages);
    }

    public function test_source_has_no_legacy_engine_or_infrastructure_dependency(): void
    {
        $reflection = new ReflectionClass(PlayerCardZonesMover::class);
        $source = file_get_contents((string) $reflection->getFileName());

        self::assertIsString($source);
        foreach ([
            'DuelPlayerState', 'DuelState', '\\Engine;', 'Engine::', 'Rng', 'Database', 'Repository', 'Http', 'Laravel',
            'Catalog', 'Effect', 'Owner', 'Controller', 'CardPosition', 'MONSTER_ZONE', 'SPELL_TRAP_ZONE',
            'FIELD_ZONE', 'static $', 'random', 'time(', 'date(',
        ] as $forbiddenDependency) {
            self::assertStringNotContainsString($forbiddenDependency, $source);
        }
    }

    private static function assertFailureIsAtomic(
        PlayerCardZones $zones,
        CardInstanceId $cardId,
        CardLocation $source,
        CardLocation $destination,
        int $destinationIndex,
        string $expectedMessage,
    ): void {
        $before = $zones->toArray();
        $zoneReferences = $zones->zones();
        $cardsBefore = array_map(
            static fn (OrderedCardZone $zone): array => $zone->cards(),
            $zoneReferences,
        );

        try {
            (new PlayerCardZonesMover)->move($zones, $cardId, $source, $destination, $destinationIndex);
            self::fail('Movimentação inválida foi aceita.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());
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
        self::assertSame([], (new ReflectionClass(PlayerCardZonesMover::class))->getStaticProperties());
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
        $definition = self::definition();

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

    private static function definition(): SpellCardDefinition
    {
        return new SpellCardDefinition('fictional-spell', 'Magia Fictícia', 'Texto fictício.', SpellType::NORMAL);
    }
}
