<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Players\DuelPlayerState;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use DuelLegacy\DuelEngine\Zones\MonsterZones;
use DuelLegacy\DuelEngine\Zones\OrderedCardZone;
use DuelLegacy\DuelEngine\Zones\SpellTrapZones;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use stdClass;

use function DuelLegacy\DuelEngine\createInitialDuelState;
use function DuelLegacy\DuelEngine\createInitialPlayerState;
use function DuelLegacy\DuelEngine\gxLegacyProfile;
use function DuelLegacy\DuelEngine\processStandbyPhase;

final class AuthoritativeSpellTrapZonesTest extends TestCase
{
    /** @return iterable<string, array{mixed}> */
    public static function invalidWithValues(): iterable
    {
        yield 'array legado' => [[null, 'legacy-id', null]];
        yield 'string' => ['legacy-id'];
        yield 'inteiro' => [5];
        yield 'null' => [null];
        yield 'MonsterZones' => [MonsterZones::empty(5)];
        yield 'PlayerCardZones' => [TestFactory::playerCardZones()];
        yield 'OrderedCardZone' => [new OrderedCardZone(CardLocation::HAND)];
        yield 'CardInstance' => [TestFactory::card('card')];
        yield 'objeto arbitrário' => [new stdClass];
    }

    public function test_player_stores_only_the_exact_typed_aggregate_reference(): void
    {
        $zones = TestFactory::spellTrapZones([null, $card = TestFactory::card('SPELL'), null]);
        $player = new DuelPlayerState(
            'p1',
            8000,
            TestFactory::playerCardZones(),
            MonsterZones::empty(3),
            $zones,
            null,
            0,
            1,
        );
        $reflection = new ReflectionClass($player);
        $propertyType = $reflection->getProperty('spellTrapZones')->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $propertyType);
        self::assertSame(SpellTrapZones::class, $propertyType->getName());
        self::assertSame($zones, $player->spellTrapZones);
        self::assertSame($card, $player->spellTrapZones->get(1));
        self::assertSame($card->definition, $player->spellTrapZones->get(1)->definition);
        self::assertSame([
            'playerId', 'lifePoints', 'cardZones', 'monsterZones', 'spellTrapZones',
            'fieldZone', 'normalSummonsUsed', 'normalSummonLimit',
        ], array_map(
            static fn (ReflectionProperty $candidate): string => $candidate->getName(),
            $reflection->getProperties(),
        ));
        foreach (['spellTrapZoneIds', 'legacySpellTrapZones', 'typedSpellTrapZones', 'spellTrapZoneCards', 'spellTrapZonesSnapshot', 'spellTrapZonesArray'] as $parallelProperty) {
            self::assertFalse($reflection->hasProperty($parallelProperty));
            self::assertFalse($reflection->hasMethod('get'.ucfirst($parallelProperty)));
        }
    }

    public function test_with_preserves_or_uses_spell_trap_zone_references_exactly(): void
    {
        $player = TestFactory::richPlayer('player-1');
        $replacement = TestFactory::spellTrapZones([TestFactory::card('replacement'), null, null, null, null]);

        self::assertSame($player->spellTrapZones, $player->with(['lifePoints' => 1])->spellTrapZones);
        self::assertSame(
            $player->spellTrapZones,
            $player->with(['normalSummonsUsed' => 2, 'normalSummonLimit' => 3])->spellTrapZones,
        );
        self::assertSame($replacement, $player->with(['spellTrapZones' => $replacement])->spellTrapZones);
        self::assertSame('player-1-spell', $player->spellTrapZones->get(2)?->id->value);
        self::assertSame(7100, $player->lifePoints);
    }

    #[DataProvider('invalidWithValues')]
    public function test_with_rejects_legacy_arrays_and_every_other_type_atomically(mixed $value): void
    {
        $player = TestFactory::richPlayer('player-1');
        $snapshot = $player->toArray();
        $zones = $player->spellTrapZones;

        try {
            $player->with(['spellTrapZones' => $value, 'lifePoints' => 1]);
            self::fail('Tipo inválido de spellTrapZones foi aceito.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('spellTrapZones deve ser uma instância de SpellTrapZones.', $exception->getMessage());
            self::assertSame($snapshot, $player->toArray());
            self::assertSame($zones, $player->spellTrapZones);
        }
    }

    public function test_player_and_duel_serialization_preserve_historical_ids_nulls_and_key_order(): void
    {
        $first = TestFactory::card('SPELL_A');
        $second = TestFactory::card('TRAP_B');
        $player = TestFactory::player('p1')->with([
            'spellTrapZones' => TestFactory::spellTrapZones([null, $first, null, $second, null]),
        ]);
        $serialized = $player->toArray();

        self::assertSame([
            'playerId', 'lifePoints', 'mainDeck', 'hand', 'graveyard', 'banishedFaceUp',
            'banishedFaceDown', 'extraDeckFaceDown', 'extraDeckFaceUp', 'monsterZones',
            'spellTrapZones', 'fieldZone', 'normalSummonsUsed', 'normalSummonLimit',
        ], array_keys($serialized));
        self::assertSame([null, 'SPELL_A', null, 'TRAP_B', null], $serialized['spellTrapZones']);
        self::assertStringNotContainsString('definition', json_encode($serialized['spellTrapZones'], JSON_THROW_ON_ERROR));
        self::assertSame([], SpellTrapZones::empty(0)->toArray());

        $duel = createInitialDuelState(
            'duel',
            gxLegacyProfile(),
            'engine',
            'pool',
            [$player, TestFactory::player('p2')],
            'p1',
        );
        self::assertSame([null, 'SPELL_A', null, 'TRAP_B', null], $duel->toArray()['players'][0]['spellTrapZones']);
        self::assertSame($player->toArray(), $duel->players[0]->toArray());
    }

    public function test_initial_creation_uses_profile_capacity_and_creates_no_field_cards(): void
    {
        $function = new \ReflectionFunction('DuelLegacy\\DuelEngine\\createInitialPlayerState');
        self::assertCount(4, $function->getParameters());

        foreach ([0, 1, 5, 9] as $capacity) {
            $profile = TestFactory::profile(['spellTrapZones' => $capacity]);
            $main = TestFactory::cards(['main']);
            $player = createInitialPlayerState($profile, "p{$capacity}", $main, []);

            self::assertSame($capacity, $player->spellTrapZones->capacity());
            self::assertSame(array_fill(0, $capacity, null), $player->spellTrapZones->slots());
            self::assertSame(0, $player->spellTrapZones->occupiedCount());
            self::assertTrue($player->spellTrapZones->isEmpty());
            self::assertSame($main, $player->cardZones->mainDeck->cards());
            self::assertInstanceOf(MonsterZones::class, $player->monsterZones);
            self::assertNull($player->fieldZone);

            $other = createInitialPlayerState($profile, "other-{$capacity}", TestFactory::cards(["other-main-{$capacity}"]), []);
            $duel = createInitialDuelState("duel-{$capacity}", $profile, 'engine', 'pool', [$player, $other], "p{$capacity}");
            self::assertSame($capacity, $duel->players[0]->spellTrapZones->capacity());
        }
    }

    public function test_engine_validates_capacity_not_occupancy_against_rules_profile(): void
    {
        $occupied = TestFactory::spellTrapZones([
            TestFactory::card('S1'),
            TestFactory::card('S2'),
            TestFactory::card('S3'),
            TestFactory::card('S4'),
            TestFactory::card('S5'),
        ]);
        $first = TestFactory::player('p1')->with(['spellTrapZones' => $occupied]);
        $duel = createInitialDuelState('duel', gxLegacyProfile(), 'engine', 'pool', [$first, TestFactory::player('p2')], 'p1');

        self::assertSame(5, $duel->players[0]->spellTrapZones->occupiedCount());
        self::assertSame($occupied, $duel->players[0]->spellTrapZones);

        foreach ([
            $first->with(['spellTrapZones' => SpellTrapZones::empty(4)]),
            $first->with(['monsterZones' => MonsterZones::empty(4)]),
        ] as $invalid) {
            $snapshot = $invalid->toArray();
            try {
                createInitialDuelState('duel', gxLegacyProfile(), 'engine', 'pool', [$invalid, TestFactory::player('p2')], 'p1');
                self::fail('Capacidade incompatível foi aceita.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('As zonas do jogador são incompatíveis com o perfil.', $exception->getMessage());
                self::assertSame($snapshot, $invalid->toArray());
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function globalDuplicateAreas(): iterable
    {
        yield 'PlayerCardZones e SpellTrapZones' => ['card-zones'];
        yield 'MonsterZones e SpellTrapZones' => ['monster-zones'];
        yield 'SpellTrapZones e fieldZone' => ['field'];
        yield 'SpellTrapZones de jogadores diferentes' => ['other-player'];
    }

    #[DataProvider('globalDuplicateAreas')]
    public function test_global_uniqueness_crosses_every_spell_trap_zone_boundary(string $area): void
    {
        $duplicate = 'DUPLICATE';
        $first = TestFactory::player('p1');
        $second = TestFactory::player('p2');

        if ($area === 'card-zones') {
            $duplicate = $first->cardZones->mainDeck->cards()[0]->id->value;
            $first = $first->with(['spellTrapZones' => TestFactory::spellTrapZones([TestFactory::card($duplicate), null, null, null, null])]);
        } elseif ($area === 'monster-zones') {
            $first = $first->with([
                'monsterZones' => TestFactory::monsterZones([TestFactory::card($duplicate), null, null, null, null]),
                'spellTrapZones' => TestFactory::spellTrapZones([null, TestFactory::card($duplicate), null, null, null]),
            ]);
        } elseif ($area === 'field') {
            $first = $first->with([
                'spellTrapZones' => TestFactory::spellTrapZones([null, TestFactory::card($duplicate), null, null, null]),
                'fieldZone' => $duplicate,
            ]);
        } else {
            $first = $first->with(['spellTrapZones' => TestFactory::spellTrapZones([TestFactory::card($duplicate), null, null, null, null])]);
            $second = $second->with(['spellTrapZones' => TestFactory::spellTrapZones([null, null, TestFactory::card($duplicate), null, null])]);
        }

        $firstSnapshot = $first->toArray();
        $secondSnapshot = $second->toArray();
        try {
            createInitialDuelState('duel', gxLegacyProfile(), 'engine', 'pool', [$first, $second], 'p1');
            self::fail("Duplicidade global em {$area} foi aceita.");
        } catch (InvalidArgumentException $exception) {
            self::assertSame('IDs de instância de carta devem ser únicos no Duelo.', $exception->getMessage());
            self::assertSame($firstSnapshot, $first->toArray());
            self::assertSame($secondSnapshot, $second->toArray());
        }
    }

    public function test_global_uniqueness_preserves_case_spaces_numeric_strings_and_unicode_exactly(): void
    {
        $ids = ['CARD', 'card', ' CARD', 'CARD ', '1', '01', 'café', "cafe\u{0301}"];
        $first = TestFactory::player('p1')->with([
            'spellTrapZones' => TestFactory::spellTrapZones(array_map(
                static fn (string $id): CardInstance => TestFactory::card($id),
                array_slice($ids, 0, 4),
            )),
        ]);
        $second = TestFactory::player('p2')->with([
            'spellTrapZones' => TestFactory::spellTrapZones(array_map(
                static fn (string $id): CardInstance => TestFactory::card($id),
                array_slice($ids, 4),
            )),
        ]);
        $profile = TestFactory::profile(['spellTrapZones' => 4]);
        $duel = createInitialDuelState('duel', $profile, 'engine', 'pool', [$first, $second], 'p1');

        self::assertSame(array_slice($ids, 0, 4), $duel->players[0]->toArray()['spellTrapZones']);
        self::assertSame(array_slice($ids, 4), $duel->players[1]->toArray()['spellTrapZones']);
    }

    public function test_engine_and_public_operations_share_all_three_immutable_zone_aggregates(): void
    {
        $first = TestFactory::richPlayer('player-1');
        $second = TestFactory::richPlayer('player-2');
        $duel = createInitialDuelState('duel', gxLegacyProfile(), 'engine', 'pool', [$first, $second], 'player-1');

        foreach ([0 => $first, 1 => $second] as $index => $source) {
            self::assertNotSame($source, $duel->players[$index]);
            self::assertSame($source->cardZones, $duel->players[$index]->cardZones);
            self::assertSame($source->monsterZones, $duel->players[$index]->monsterZones);
            self::assertSame($source->spellTrapZones, $duel->players[$index]->spellTrapZones);
            self::assertSame($source->spellTrapZones->get(2), $duel->players[$index]->spellTrapZones->get(2));
            self::assertSame($source->spellTrapZones->get(2)?->definition, $duel->players[$index]->spellTrapZones->get(2)?->definition);
        }

        $active = TestFactory::activeDuel(DuelPhase::STANDBY, 2);
        $result = processStandbyPhase($active, gxLegacyProfile());
        foreach ([0, 1] as $index) {
            self::assertNotSame($active->players[$index], $result->players[$index]);
            self::assertSame($active->players[$index]->cardZones, $result->players[$index]->cardZones);
            self::assertSame($active->players[$index]->monsterZones, $result->players[$index]->monsterZones);
            self::assertSame($active->players[$index]->spellTrapZones, $result->players[$index]->spellTrapZones);
            self::assertSame($active->players[$index]->spellTrapZones->get(2), $result->players[$index]->spellTrapZones->get(2));
        }
    }
}
