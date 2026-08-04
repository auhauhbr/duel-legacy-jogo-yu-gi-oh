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
use DuelLegacy\DuelEngine\Zones\MonsterZones;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;

final class MonsterZonesTest extends TestCase
{
    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidLists(): iterable
    {
        $card = self::instance('A', self::spellDefinition());

        yield 'array associativo' => [['slot' => $card]];
        yield 'índice inicial diferente de zero' => [[1 => $card]];
        yield 'lacuna' => [[0 => null, 2 => $card]];
    }

    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidElements(): iterable
    {
        $card = self::instance('A', self::spellDefinition());

        yield 'string' => [['A']];
        yield 'inteiro' => [[1]];
        yield 'objeto diferente' => [[new stdClass]];
        yield 'conteúdo misturado' => [[null, $card, 'B']];
    }

    /** @return iterable<string, array{int}> */
    public static function capacities(): iterable
    {
        yield 'zero' => [0];
        yield 'um' => [1];
        yield 'cinco' => [5];
        yield 'maior que cinco' => [12];
    }

    /** @return iterable<string, array{int}> */
    public static function invalidIndices(): iterable
    {
        yield 'negativo' => [-1];
        yield 'igual à capacidade' => [3];
        yield 'maior que a capacidade' => [99];
    }

    /** @return iterable<string, array{int}> */
    public static function slotIndices(): iterable
    {
        yield 'primeira posição' => [0];
        yield 'posição intermediária' => [2];
        yield 'última posição' => [4];
    }

    /** @return iterable<string, array{CardDefinition}> */
    public static function cardDefinitions(): iterable
    {
        yield 'Monstro' => [self::monsterDefinition()];
        yield 'Magia' => [self::spellDefinition()];
        yield 'Armadilha' => [self::trapDefinition()];
    }

    public function test_structure_is_final_readonly_directly_instantiable_and_documents_real_slot_list(): void
    {
        $zones = new MonsterZones([]);
        $reflection = new ReflectionClass($zones);
        $constructor = $reflection->getConstructor();

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPublic());
        self::assertSame(['slots'], array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            $reflection->getProperties(),
        ));
        self::assertSame([], $reflection->getProperties(ReflectionProperty::IS_PUBLIC));
        self::assertSame([], $reflection->getStaticProperties());
        self::assertSame('slots', $constructor->getParameters()[0]->getName());
        self::assertSame('array', (string) $constructor->getParameters()[0]->getType());
        self::assertIsString($constructor->getDocComment());
        self::assertStringContainsString('list<?CardInstance>', $constructor->getDocComment());
        self::assertSame([], array_values(array_filter(
            $reflection->getMethods(),
            static fn (ReflectionMethod $method): bool => str_starts_with($method->getName(), 'set'),
        )));
    }

    public function test_constructs_empty_null_only_and_interleaved_fixed_positions(): void
    {
        $a = self::instance('A', self::monsterDefinition());
        $b = self::instance('B', self::spellDefinition());
        $empty = new MonsterZones([]);
        $oneEmpty = new MonsterZones([null]);
        $interleaved = new MonsterZones([null, $a, null, $b, null]);

        self::assertSame([], $empty->slots());
        self::assertSame([null], $oneEmpty->slots());
        self::assertSame([null, $a, null, $b, null], $interleaved->slots());
        self::assertSame([0, 1, 2, 3, 4], array_keys($interleaved->slots()));
        self::assertSame($a, $interleaved->get(1));
        self::assertSame($a->definition, $interleaved->get(1)->definition);
        self::assertSame($b, $interleaved->get(3));
        self::assertNull($interleaved->get(2));
    }

    /** @param array<array-key, mixed> $slots */
    #[DataProvider('invalidLists')]
    public function test_rejects_every_non_list_with_exact_message(array $slots): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('slots deve ser uma lista.');

        self::constructUnchecked($slots);
    }

    /** @param array<array-key, mixed> $slots */
    #[DataProvider('invalidElements')]
    public function test_rejects_every_invalid_element_with_exact_message(array $slots): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('slots deve conter apenas CardInstance ou null.');

        self::constructUnchecked($slots);
    }

    public function test_rejects_same_reference_and_distinct_instances_with_duplicate_exact_id(): void
    {
        $same = self::instance('duplicate', self::spellDefinition());

        foreach ([[$same, null, $same], [
            self::instance('duplicate', self::monsterDefinition()),
            self::instance('duplicate', self::trapDefinition()),
        ]] as $slots) {
            try {
                new MonsterZones($slots);
                self::fail('CardInstanceId duplicado foi aceito.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('CardInstanceId duplicado nas Zonas de Monstro: duplicate.', $exception->getMessage());
            }
        }
    }

    public function test_duplicate_keys_are_string_safe_case_sensitive_and_preserve_spaces_and_unicode(): void
    {
        $ids = ['1', '01', 'CARD', 'card', ' CARD', 'CARD ', 'café', "cafe\u{0301}"];
        $cards = array_map(
            static fn (string $id): CardInstance => self::instance($id, self::spellDefinition()),
            $ids,
        );
        $zones = new MonsterZones($cards);

        self::assertSame($ids, array_map(
            static fn (?CardInstance $card): ?string => $card?->id->value,
            $zones->slots(),
        ));

        $this->expectExceptionMessage('CardInstanceId duplicado nas Zonas de Monstro: 1.');
        new MonsterZones([
            self::instance('1', self::spellDefinition()),
            self::instance('1', self::trapDefinition()),
        ]);
    }

    #[DataProvider('capacities')]
    public function test_empty_creates_the_exact_requested_number_of_null_slots_without_fixed_limit(int $capacity): void
    {
        $zones = MonsterZones::empty($capacity);

        self::assertSame($capacity, $zones->capacity());
        self::assertCount($capacity, $zones->slots());
        self::assertSame(array_fill(0, $capacity, null), $zones->slots());
        self::assertSame(0, $zones->occupiedCount());
        self::assertTrue($zones->isEmpty());
    }

    public function test_empty_rejects_negative_capacity_with_exact_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A capacidade das Zonas de Monstro não pode ser negativa: -1.');

        MonsterZones::empty(-1);
    }

    public function test_read_api_counts_capacity_and_occupants_and_queries_exact_ids(): void
    {
        $first = self::instance('CARD', self::monsterDefinition());
        $middle = self::instance(' card ', self::spellDefinition());
        $last = self::instance('café', self::trapDefinition());
        $zones = new MonsterZones([$first, null, $middle, null, $last]);

        self::assertSame(5, $zones->capacity());
        self::assertSame(3, $zones->occupiedCount());
        self::assertFalse($zones->isEmpty());
        self::assertTrue($zones->contains(new CardInstanceId('CARD')));
        self::assertFalse($zones->contains(new CardInstanceId('card')));
        self::assertTrue($zones->contains(new CardInstanceId(' card ')));
        self::assertSame($first, $zones->find(new CardInstanceId('CARD')));
        self::assertSame($middle, $zones->find(new CardInstanceId(' card ')));
        self::assertSame($last, $zones->find(new CardInstanceId('café')));
        self::assertNull($zones->find(new CardInstanceId("cafe\u{0301}")));
        self::assertSame(0, $zones->indexOf(new CardInstanceId('CARD')));
        self::assertSame(2, $zones->indexOf(new CardInstanceId(' card ')));
        self::assertSame(4, $zones->indexOf(new CardInstanceId('café')));
        self::assertNull($zones->indexOf(new CardInstanceId('missing')));
    }

    #[DataProvider('invalidIndices')]
    public function test_get_rejects_invalid_indices_instead_of_returning_null(int $index): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Índice das Zonas de Monstro fora do intervalo: {$index}.");

        MonsterZones::empty(3)->get($index);
    }

    #[DataProvider('slotIndices')]
    public function test_with_slot_occupies_first_middle_and_last_positions_immutably(int $index): void
    {
        $zones = MonsterZones::empty(5);
        $card = self::instance("card-{$index}", self::spellDefinition());
        $result = $zones->withSlot($index, $card);

        self::assertNotSame($zones, $result);
        self::assertSame(5, $result->capacity());
        self::assertSame(1, $result->occupiedCount());
        self::assertSame($card, $result->get($index));
        self::assertSame($card->definition, $result->get($index)->definition);
        self::assertSame(array_fill(0, 5, null), $zones->slots());
        foreach ($result->slots() as $slotIndex => $occupant) {
            if ($slotIndex !== $index) {
                self::assertNull($occupant);
            }
        }
    }

    public function test_with_slot_replaces_empties_and_preserves_every_unaffected_reference(): void
    {
        $first = self::instance('first', self::monsterDefinition());
        $old = self::instance('old', self::spellDefinition());
        $last = self::instance('last', self::trapDefinition());
        $replacement = self::instance('replacement', self::monsterDefinition());
        $zones = new MonsterZones([$first, null, $old, null, $last]);
        $replaced = $zones->withSlot(2, $replacement);
        $emptied = $replaced->withSlot(2, null);

        self::assertSame([$first, null, $old, null, $last], $zones->slots());
        self::assertSame([$first, null, $replacement, null, $last], $replaced->slots());
        self::assertSame([$first, null, null, null, $last], $emptied->slots());
        self::assertSame($first, $replaced->get(0));
        self::assertSame($last, $replaced->get(4));
        self::assertSame($first->definition, $replaced->get(0)->definition);
        self::assertSame($last->definition, $replaced->get(4)->definition);
        self::assertSame(5, $emptied->capacity());
    }

    public function test_with_slot_same_reference_and_null_over_null_return_same_aggregate(): void
    {
        $card = self::instance('same', self::spellDefinition());
        $zones = new MonsterZones([$card, null]);

        self::assertSame($zones, $zones->withSlot(0, $card));
        self::assertSame($zones, $zones->withSlot(1, null));
    }

    public function test_with_slot_rejects_resulting_duplicate_and_keeps_original_unchanged(): void
    {
        $first = self::instance('duplicate', self::spellDefinition());
        $second = self::instance('other', self::trapDefinition());
        $zones = new MonsterZones([$first, null, $second]);
        $snapshot = $zones->toArray();

        try {
            $zones->withSlot(1, self::instance('duplicate', self::monsterDefinition()));
            self::fail('Duplicidade resultante foi aceita.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('CardInstanceId duplicado nas Zonas de Monstro: duplicate.', $exception->getMessage());
            self::assertSame($snapshot, $zones->toArray());
            self::assertSame($first, $zones->get(0));
            self::assertSame($second, $zones->get(2));
        }
    }

    #[DataProvider('invalidIndices')]
    public function test_with_slot_rejects_invalid_indices_atomically(int $index): void
    {
        $zones = MonsterZones::empty(3);
        $snapshot = $zones->slots();

        try {
            $zones->withSlot($index, self::instance('A', self::spellDefinition()));
            self::fail('Índice inválido foi aceito.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame("Índice das Zonas de Monstro fora do intervalo: {$index}.", $exception->getMessage());
            self::assertSame($snapshot, $zones->slots());
        }
    }

    public function test_slots_returns_a_defensive_array_while_preserving_card_and_definition_identity(): void
    {
        $card = self::instance('A', self::spellDefinition());
        $replacement = self::instance('B', self::trapDefinition());
        $input = [null, $card, null];
        $zones = new MonsterZones($input);

        $input[1] = $replacement;
        $returned = $zones->slots();
        $returned[0] = $replacement;
        $returned[1] = null;
        array_pop($returned);

        self::assertSame([null, $card, null], $zones->slots());
        self::assertSame($card, $zones->get(1));
        self::assertSame($card->definition, $zones->get(1)->definition);
    }

    public function test_own_serialization_preserves_null_order_indices_and_full_card_definitions(): void
    {
        $monster = self::instance('MONSTER', self::monsterDefinition());
        $spell = self::instance('SPELL', self::spellDefinition());
        $zones = new MonsterZones([null, $monster, null, $spell, null]);

        self::assertSame([
            null,
            $monster->toArray(),
            null,
            $spell->toArray(),
            null,
        ], $zones->toArray());
        self::assertSame([0, 1, 2, 3, 4], array_keys($zones->toArray()));
        self::assertSame('MONSTER', $zones->toArray()[1]['definition']['kind']);
        self::assertSame('SPELL', $zones->toArray()[3]['definition']['kind']);
        self::assertSame([], MonsterZones::empty(0)->toArray());
    }

    #[DataProvider('cardDefinitions')]
    public function test_accepts_every_printed_card_definition_without_special_behavior(CardDefinition $definition): void
    {
        $card = self::instance('occupant', $definition);
        $zones = new MonsterZones([$card]);

        self::assertSame($card, $zones->get(0));
        self::assertSame($definition, $zones->get(0)->definition);
        $serialized = $zones->toArray();
        self::assertIsArray($serialized[0]);
        self::assertSame($definition->kind->value, $serialized[0]['definition']['kind']);
    }

    public function test_source_has_only_the_minimal_card_identity_dependencies(): void
    {
        $reflection = new ReflectionClass(MonsterZones::class);
        $source = file_get_contents((string) $reflection->getFileName());

        self::assertIsString($source);
        self::assertStringContainsString('namespace DuelLegacy\\DuelEngine\\Zones;', $source);
        foreach ([
            'DuelPlayerState', 'DuelState', 'use DuelLegacy\\DuelEngine\\Engine;', 'Engine::',
            'RulesProfile', 'PlayerCardZones', 'OrderedCardZone',
            'PlayerCardZonesMover', 'Laravel', 'Http', 'Database', 'Repository', 'Catalog', 'Rng',
            'Phase', 'Turn', 'Effect', 'Chain', 'CardLocation', 'static $',
        ] as $forbiddenDependency) {
            self::assertStringNotContainsString($forbiddenDependency, $source);
        }
    }

    /** @param array<array-key, mixed> $slots */
    private static function constructUnchecked(array $slots): MonsterZones
    {
        $result = (new ReflectionClass(MonsterZones::class))->newInstance($slots);
        self::assertInstanceOf(MonsterZones::class, $result);

        return $result;
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
