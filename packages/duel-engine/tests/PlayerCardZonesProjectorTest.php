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
use DuelLegacy\DuelEngine\Players\DuelPlayerState;
use DuelLegacy\DuelEngine\Players\PlayerCardZonesProjector;
use DuelLegacy\DuelEngine\Zones\OrderedCardZone;
use DuelLegacy\DuelEngine\Zones\PlayerCardZones;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use stdClass;

final class PlayerCardZonesProjectorTest extends TestCase
{
    /** @return iterable<string, array{array<array-key, mixed>, string}> */
    public static function invalidAvailableCollections(): iterable
    {
        $card = self::instance('instance-001', self::spellDefinition());

        yield 'array associativo' => [['card' => $card], 'availableInstances deve ser uma lista.'];
        yield 'índices com lacunas' => [[0 => $card, 2 => $card], 'availableInstances deve ser uma lista.'];
        yield 'elemento string' => [['instance-001'], 'availableInstances deve conter apenas CardInstance.'];
        yield 'objeto arbitrário' => [[new stdClass], 'availableInstances deve conter apenas CardInstance.'];
    }

    /** @return iterable<string, array{string, CardLocation}> */
    public static function unresolvedLegacyZones(): iterable
    {
        yield 'Deck Principal' => ['mainDeck', CardLocation::MAIN_DECK];
        yield 'mão' => ['hand', CardLocation::HAND];
        yield 'Cemitério' => ['graveyard', CardLocation::GRAVEYARD];
        yield 'banidas para cima' => ['banishedFaceUp', CardLocation::BANISHED_FACE_UP];
        yield 'banidas para baixo' => ['banishedFaceDown', CardLocation::BANISHED_FACE_DOWN];
        yield 'Deck Adicional para baixo' => ['extraDeckFaceDown', CardLocation::EXTRA_DECK_FACE_DOWN];
        yield 'Deck Adicional para cima' => ['extraDeckFaceUp', CardLocation::EXTRA_DECK_FACE_UP];
    }

    public function test_constructs_with_empty_one_and_multiple_instances_of_every_kind(): void
    {
        $monsterDefinition = self::monsterDefinition();
        $monsterOne = self::instance('monster-001', $monsterDefinition);
        $monsterTwo = self::instance('monster-002', $monsterDefinition);
        $spell = self::instance('spell-001', self::spellDefinition());
        $trap = self::instance('trap-001', self::trapDefinition());

        self::assertInstanceOf(PlayerCardZonesProjector::class, new PlayerCardZonesProjector([]));
        self::assertInstanceOf(PlayerCardZonesProjector::class, new PlayerCardZonesProjector([$monsterOne]));
        self::assertInstanceOf(
            PlayerCardZonesProjector::class,
            new PlayerCardZonesProjector([$monsterOne, $spell, $trap, $monsterTwo]),
        );
        self::assertSame($monsterDefinition, $monsterOne->definition);
        self::assertSame($monsterDefinition, $monsterTwo->definition);
    }

    /** @param array<array-key, mixed> $availableInstances */
    #[DataProvider('invalidAvailableCollections')]
    public function test_rejects_invalid_available_collection_structure(array $availableInstances, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        self::constructWithUncheckedInstances($availableInstances);
    }

    public function test_rejects_duplicate_instance_ids_with_a_deterministic_message(): void
    {
        $definition = self::trapDefinition();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CardInstanceId duplicado na coleção disponível: repeated-id.');

        new PlayerCardZonesProjector([
            self::instance('repeated-id', $definition),
            self::instance('other-id', $definition),
            self::instance('repeated-id', $definition),
        ]);
    }

    public function test_available_ids_are_compared_exactly_without_case_or_space_normalization(): void
    {
        $definition = self::spellDefinition();
        $plain = self::instance('instance-id', $definition);
        $upper = self::instance('INSTANCE-ID', $definition);
        $spaced = self::instance(' instance-id ', $definition);
        $state = self::emptyPlayer()->with([
            'mainDeck' => ['instance-id', 'INSTANCE-ID', ' instance-id '],
        ]);

        $zones = (new PlayerCardZonesProjector([$plain, $upper, $spaced]))->project($state);

        self::assertSame([$plain, $upper, $spaced], $zones->mainDeck->cards());
        self::assertSame(
            ['instance-id', 'INSTANCE-ID', ' instance-id '],
            array_column($zones->mainDeck->toArray()['cards'], 'id'),
        );
    }

    public function test_projects_an_empty_player_to_the_seven_correct_empty_zones(): void
    {
        $state = self::emptyPlayer();
        $before = $state->toArray();
        $zones = (new PlayerCardZonesProjector([]))->project($state);

        self::assertSame(self::locations(), array_map(
            static fn (OrderedCardZone $zone): CardLocation => $zone->location,
            $zones->zones(),
        ));
        self::assertCount(7, $zones->zones());
        foreach ($zones->zones() as $zone) {
            self::assertSame(0, $zone->count());
            self::assertTrue($zone->isEmpty());
        }
        self::assertSame(0, $zones->count());
        self::assertSame(self::emptySerializedZones(), $zones->toArray());
        self::assertSame($zones->toArray(), (new PlayerCardZonesProjector([]))->project(self::emptyPlayer())->toArray());
        self::assertSame($before, $state->toArray());
    }

    public function test_projects_every_legacy_zone_directly_and_preserves_order_and_references(): void
    {
        $monsterDefinition = self::monsterDefinition();
        $spellDefinition = self::spellDefinition();
        $trapDefinition = self::trapDefinition();
        $instances = [
            'deck-bottom' => self::instance('deck-bottom', $trapDefinition),
            'hand-second' => self::instance('hand-second', $monsterDefinition),
            'banished-down' => self::instance('banished-down', $spellDefinition),
            'banished-down-second' => self::instance('banished-down-second', $monsterDefinition),
            'deck-top' => self::instance('deck-top', $monsterDefinition),
            'grave-second' => self::instance('grave-second', $trapDefinition),
            'extra-up' => self::instance('extra-up', $monsterDefinition),
            'extra-up-second' => self::instance('extra-up-second', $trapDefinition),
            'hand-first' => self::instance('hand-first', $spellDefinition),
            'banished-up' => self::instance('banished-up', $trapDefinition),
            'banished-up-second' => self::instance('banished-up-second', $spellDefinition),
            'extra-down-second' => self::instance('extra-down-second', $spellDefinition),
            'grave-first' => self::instance('grave-first', $monsterDefinition),
            'extra-down-first' => self::instance('extra-down-first', $monsterDefinition),
        ];
        $available = array_values($instances);
        $state = self::emptyPlayer()->with([
            'mainDeck' => ['deck-top', 'deck-bottom'],
            'hand' => ['hand-first', 'hand-second'],
            'graveyard' => ['grave-first', 'grave-second'],
            'banishedFaceUp' => ['banished-up', 'banished-up-second'],
            'banishedFaceDown' => ['banished-down', 'banished-down-second'],
            'extraDeckFaceDown' => ['extra-down-first', 'extra-down-second'],
            'extraDeckFaceUp' => ['extra-up', 'extra-up-second'],
            'monsterZones' => ['field-monster', null, null, null, null],
            'spellTrapZones' => [null, 'field-spell', null, null, null],
            'fieldZone' => 'field-zone',
        ]);
        $before = $state->toArray();

        $zones = (new PlayerCardZonesProjector($available))->project($state);

        self::assertSame(['deck-top', 'deck-bottom'], self::ids($zones->mainDeck));
        self::assertSame(['hand-first', 'hand-second'], self::ids($zones->hand));
        self::assertSame(['grave-first', 'grave-second'], self::ids($zones->graveyard));
        self::assertSame(['banished-up', 'banished-up-second'], self::ids($zones->banishedFaceUp));
        self::assertSame(['banished-down', 'banished-down-second'], self::ids($zones->banishedFaceDown));
        self::assertSame(['extra-down-first', 'extra-down-second'], self::ids($zones->extraDeckFaceDown));
        self::assertSame(['extra-up', 'extra-up-second'], self::ids($zones->extraDeckFaceUp));
        self::assertSame($instances['deck-top'], $zones->mainDeck->cards()[0]);
        self::assertSame($monsterDefinition, $zones->mainDeck->cards()[0]->definition);
        self::assertSame($instances['deck-bottom'], $zones->mainDeck->cards()[1]);
        self::assertSame($trapDefinition, $zones->mainDeck->cards()[1]->definition);
        foreach ($zones->zones() as $zone) {
            foreach ($zone->cards() as $card) {
                self::assertSame($instances[$card->id->value], $card);
                self::assertSame($instances[$card->id->value]->definition, $card->definition);
            }
        }
        self::assertSame($before, $state->toArray());
        self::assertSame(array_values($instances), $available);
    }

    public function test_delegates_duplicate_ids_between_projected_zones_to_player_card_zones(): void
    {
        $card = self::instance('duplicated-id', self::monsterDefinition());
        $state = self::emptyPlayer()->with([
            'mainDeck' => ['duplicated-id'],
            'hand' => ['duplicated-id'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CardInstanceId duplicado entre MAIN_DECK e HAND: duplicated-id.');

        (new PlayerCardZonesProjector([$card]))->project($state);
    }

    public function test_extra_field_and_unused_instances_are_accepted_and_ignored(): void
    {
        $hand = self::instance('hand-card', self::spellDefinition());
        $monster = self::instance('field-monster', self::monsterDefinition());
        $spellTrap = self::instance('field-spell', self::trapDefinition());
        $unused = self::instance('unused-card', self::spellDefinition());
        $state = self::emptyPlayer()->with([
            'hand' => ['hand-card'],
            'monsterZones' => ['field-monster', null, null, null, null],
            'spellTrapZones' => ['field-spell', null, null, null, null],
        ]);

        $zones = (new PlayerCardZonesProjector([$monster, $hand, $spellTrap, $unused]))->project($state);

        self::assertSame(1, $zones->count());
        self::assertSame($hand, $zones->hand->cards()[0]);
        foreach ([$monster, $spellTrap, $unused] as $extra) {
            self::assertFalse($zones->contains($extra->id));
            self::assertNull($zones->find($extra->id));
        }
    }

    #[DataProvider('unresolvedLegacyZones')]
    public function test_rejects_an_unresolved_id_in_every_legacy_zone_without_mutation(
        string $property,
        CardLocation $location,
    ): void {
        $state = self::emptyPlayer()->with([$property => ['missing-id']]);
        $stateBefore = $state->toArray();
        $available = [self::instance('available-id', self::monsterDefinition())];
        $availableBefore = $available;
        $instanceBefore = $available[0]->toArray();

        try {
            (new PlayerCardZonesProjector($available))->project($state);
            self::fail('ID ausente foi aceito.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                "CardInstance não encontrada para o ID missing-id na localização {$location->value}.",
                $exception->getMessage(),
            );
        }

        self::assertSame($stateBefore, $state->toArray());
        self::assertSame($availableBefore, $available);
        self::assertSame($instanceBefore, $available[0]->toArray());
    }

    public function test_returned_aggregate_supports_existing_queries_and_serialization(): void
    {
        $deck = self::instance('deck-card', self::monsterDefinition());
        $hand = self::instance('hand-card', self::spellDefinition());
        $state = self::emptyPlayer()->with(['mainDeck' => ['deck-card'], 'hand' => ['hand-card']]);
        $zones = (new PlayerCardZonesProjector([$hand, $deck]))->project($state);

        self::assertCount(7, $zones->zones());
        self::assertSame($zones->hand, $zones->get(CardLocation::HAND));
        self::assertTrue($zones->contains(new CardInstanceId('deck-card')));
        self::assertSame($hand, $zones->find(new CardInstanceId('hand-card')));
        self::assertSame(2, $zones->count());
        self::assertSame(['deck-card'], array_column($zones->toArray()['mainDeck']['cards'], 'id'));
    }

    public function test_projector_and_snapshots_are_immutable_and_independent(): void
    {
        $definition = self::monsterDefinition();
        $card = self::instance('hand-card', $definition);
        $replacement = self::instance('replacement-card', self::trapDefinition());
        $available = [$card];
        $projector = new PlayerCardZonesProjector($available);
        $available[0] = $replacement;
        $available[] = $replacement;
        $state = self::emptyPlayer()->with(['hand' => ['hand-card']]);
        $stateBefore = $state->toArray();
        $cardBefore = $card->toArray();
        $definitionBefore = $definition->toArray();

        $first = $projector->project($state);
        $second = $projector->project($state);
        $changedState = $state->with(['hand' => []]);
        $changedProjection = $projector->project($changedState);
        $firstArray = $first->toArray();
        $secondArray = $second->toArray();
        $firstArray['hand']['cards'][0]['id'] = 'changed';

        self::assertNotSame($first, $second);
        self::assertNotSame($first->hand, $second->hand);
        self::assertSame($card, $first->hand->cards()[0]);
        self::assertSame($card, $second->hand->cards()[0]);
        self::assertSame($definition, $first->hand->cards()[0]->definition);
        self::assertSame($second->toArray(), $projector->project($state)->toArray());
        self::assertSame(1, $first->count());
        self::assertSame(0, $changedProjection->count());
        self::assertNotSame($firstArray, $secondArray);
        self::assertSame($stateBefore, $state->toArray());
        self::assertSame($cardBefore, $card->toArray());
        self::assertSame($definitionBefore, $definition->toArray());
    }

    public function test_projector_is_final_readonly_has_no_setters_or_static_state(): void
    {
        $projector = new PlayerCardZonesProjector([]);
        $reflection = new ReflectionClass($projector);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertSame([], array_values(array_filter(
            $reflection->getMethods(),
            static fn (ReflectionMethod $method): bool => str_starts_with($method->getName(), 'set'),
        )));
        self::assertSame(['instancesById'], array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            $reflection->getProperties(),
        ));
        self::assertFalse($reflection->getProperty('instancesById')->isStatic());

        try {
            $reflection->getProperty('instancesById')->setValue($projector, []);
            self::fail('Índice readonly foi substituído.');
        } catch (\Error) {
            self::assertSame(self::emptySerializedZones(), $projector->project(self::emptyPlayer())->toArray());
        }
    }

    public function test_public_api_is_the_narrow_read_only_projection_boundary(): void
    {
        $reflection = new ReflectionClass(PlayerCardZonesProjector::class);
        $publicMethods = array_values(array_filter(
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === PlayerCardZonesProjector::class,
        ));
        $project = $reflection->getMethod('project');
        $parameterType = $project->getParameters()[0]->getType();
        $returnType = $project->getReturnType();

        self::assertSame(['__construct', 'project'], array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $publicMethods,
        ));
        self::assertInstanceOf(ReflectionNamedType::class, $parameterType);
        self::assertSame(DuelPlayerState::class, $parameterType->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame(PlayerCardZones::class, $returnType->getName());

        $source = file_get_contents((string) $reflection->getFileName());
        self::assertIsString($source);
        foreach ([
            'Database', 'Repository', 'Catalog', 'static $', '\\Engine;', 'Engine::', 'Rng', 'Http', 'Laravel',
            '->with(', 'move', 'effect',
        ] as $forbiddenDependency) {
            self::assertStringNotContainsString($forbiddenDependency, $source);
        }
    }

    /** @param array<array-key, mixed> $availableInstances */
    private static function constructWithUncheckedInstances(array $availableInstances): PlayerCardZonesProjector
    {
        return (new ReflectionClass(PlayerCardZonesProjector::class))->newInstance($availableInstances);
    }

    /** @return list<string> */
    private static function ids(OrderedCardZone $zone): array
    {
        return array_map(static fn (CardInstance $card): string => $card->id->value, $zone->cards());
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

    /** @return array<string, array{location: string, cards: array{}}> */
    private static function emptySerializedZones(): array
    {
        return [
            'mainDeck' => ['location' => 'MAIN_DECK', 'cards' => []],
            'hand' => ['location' => 'HAND', 'cards' => []],
            'graveyard' => ['location' => 'GRAVEYARD', 'cards' => []],
            'banishedFaceUp' => ['location' => 'BANISHED_FACE_UP', 'cards' => []],
            'banishedFaceDown' => ['location' => 'BANISHED_FACE_DOWN', 'cards' => []],
            'extraDeckFaceDown' => ['location' => 'EXTRA_DECK_FACE_DOWN', 'cards' => []],
            'extraDeckFaceUp' => ['location' => 'EXTRA_DECK_FACE_UP', 'cards' => []],
        ];
    }

    private static function emptyPlayer(): DuelPlayerState
    {
        return new DuelPlayerState(
            playerId: 'player-1',
            lifePoints: 8000,
            mainDeck: [],
            hand: [],
            graveyard: [],
            banishedFaceUp: [],
            banishedFaceDown: [],
            extraDeckFaceDown: [],
            extraDeckFaceUp: [],
            monsterZones: [null, null, null, null, null],
            spellTrapZones: [null, null, null, null, null],
            fieldZone: null,
            normalSummonsUsed: 0,
            normalSummonLimit: 1,
        );
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
