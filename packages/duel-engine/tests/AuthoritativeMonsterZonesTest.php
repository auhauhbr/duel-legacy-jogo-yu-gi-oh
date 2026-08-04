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

final class AuthoritativeMonsterZonesTest extends TestCase
{
    /** @return iterable<string, array{mixed}> */
    public static function invalidWithValues(): iterable
    {
        yield 'array legado' => [[null, 'legacy-id', null]];
        yield 'string' => ['legacy-id'];
        yield 'inteiro' => [5];
        yield 'null' => [null];
        yield 'PlayerCardZones' => [TestFactory::playerCardZones()];
        yield 'OrderedCardZone' => [new OrderedCardZone(CardLocation::HAND)];
        yield 'CardInstance' => [TestFactory::card('card')];
        yield 'objeto arbitrário' => [new stdClass];
    }

    public function test_player_stores_only_the_exact_typed_aggregate_reference(): void
    {
        $zones = TestFactory::monsterZones([null, $card = TestFactory::card('MONSTER'), null]);
        $player = new DuelPlayerState(
            'p1',
            8000,
            TestFactory::playerCardZones(),
            $zones,
            SpellTrapZones::empty(3),
            null,
            0,
            1,
        );
        $reflection = new ReflectionClass($player);
        $property = $reflection->getProperty('monsterZones');
        $propertyType = $property->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $propertyType);
        self::assertSame(MonsterZones::class, $propertyType->getName());
        self::assertSame($zones, $player->monsterZones);
        self::assertSame($card, $player->monsterZones->get(1));
        self::assertSame($card->definition, $player->monsterZones->get(1)->definition);
        self::assertSame([
            'playerId', 'lifePoints', 'cardZones', 'monsterZones', 'spellTrapZones',
            'fieldZone', 'normalSummonsUsed', 'normalSummonLimit',
        ], array_map(
            static fn (ReflectionProperty $candidate): string => $candidate->getName(),
            $reflection->getProperties(),
        ));
        foreach (['monsterZoneIds', 'monsterZoneCards', 'typedMonsterZones', 'legacyMonsterZones', 'monsterZonesSnapshot'] as $parallelProperty) {
            self::assertFalse($reflection->hasProperty($parallelProperty));
            self::assertFalse($reflection->hasMethod('get'.ucfirst($parallelProperty)));
        }
    }

    public function test_with_preserves_or_uses_monster_zone_references_exactly(): void
    {
        $player = TestFactory::richPlayer('player-1');
        $replacement = TestFactory::monsterZones([TestFactory::card('replacement'), null, null, null, null]);

        self::assertSame($player->monsterZones, $player->with(['lifePoints' => 1])->monsterZones);
        self::assertSame(
            $player->monsterZones,
            $player->with(['normalSummonsUsed' => 2, 'normalSummonLimit' => 3])->monsterZones,
        );
        self::assertSame($replacement, $player->with(['monsterZones' => $replacement])->monsterZones);
        self::assertSame('player-1-monster', $player->monsterZones->get(1)?->id->value);
        self::assertSame(7100, $player->lifePoints);
    }

    #[DataProvider('invalidWithValues')]
    public function test_with_rejects_legacy_arrays_and_every_other_type_atomically(mixed $value): void
    {
        $player = TestFactory::richPlayer('player-1');
        $snapshot = $player->toArray();
        $zones = $player->monsterZones;

        try {
            $player->with(['monsterZones' => $value, 'lifePoints' => 1]);
            self::fail('Tipo inválido de monsterZones foi aceito.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('monsterZones deve ser uma instância de MonsterZones.', $exception->getMessage());
            self::assertSame($snapshot, $player->toArray());
            self::assertSame($zones, $player->monsterZones);
        }
    }

    public function test_player_and_duel_serialization_preserve_historical_ids_nulls_and_key_order(): void
    {
        $first = TestFactory::card('MONSTER_A');
        $second = TestFactory::card('MONSTER_B');
        $player = TestFactory::player('p1')->with([
            'monsterZones' => TestFactory::monsterZones([null, $first, null, $second, null]),
        ]);
        $serialized = $player->toArray();

        self::assertSame([
            'playerId', 'lifePoints', 'mainDeck', 'hand', 'graveyard', 'banishedFaceUp',
            'banishedFaceDown', 'extraDeckFaceDown', 'extraDeckFaceUp', 'monsterZones',
            'spellTrapZones', 'fieldZone', 'normalSummonsUsed', 'normalSummonLimit',
        ], array_keys($serialized));
        self::assertSame([null, 'MONSTER_A', null, 'MONSTER_B', null], $serialized['monsterZones']);
        self::assertStringNotContainsString('definition', json_encode($serialized['monsterZones'], JSON_THROW_ON_ERROR));
        self::assertSame([], MonsterZones::empty(0)->toArray());

        $duel = createInitialDuelState(
            'duel',
            gxLegacyProfile(),
            'engine',
            'pool',
            [$player, TestFactory::player('p2')],
            'p1',
        );
        self::assertSame([null, 'MONSTER_A', null, 'MONSTER_B', null], $duel->toArray()['players'][0]['monsterZones']);
        self::assertSame($player->toArray(), $duel->players[0]->toArray());
    }

    public function test_initial_creation_uses_profile_capacity_and_creates_no_field_cards(): void
    {
        foreach ([0, 1, 5, 9] as $capacity) {
            $profile = TestFactory::profile(['mainMonsterZones' => $capacity]);
            $main = TestFactory::cards(['main']);
            $player = createInitialPlayerState($profile, "p{$capacity}", $main, []);

            self::assertSame($capacity, $player->monsterZones->capacity());
            self::assertSame(array_fill(0, $capacity, null), $player->monsterZones->slots());
            self::assertSame(0, $player->monsterZones->occupiedCount());
            self::assertTrue($player->monsterZones->isEmpty());
            self::assertSame($main, $player->cardZones->mainDeck->cards());
            self::assertSame([null, null, null, null, null], $player->spellTrapZones->slots());
            self::assertNull($player->fieldZone);
        }
    }

    public function test_engine_validates_capacity_not_occupancy_against_rules_profile(): void
    {
        $occupied = TestFactory::monsterZones([
            TestFactory::card('M1'),
            TestFactory::card('M2'),
            TestFactory::card('M3'),
            TestFactory::card('M4'),
            TestFactory::card('M5'),
        ]);
        $first = TestFactory::player('p1')->with(['monsterZones' => $occupied]);
        $duel = createInitialDuelState('duel', gxLegacyProfile(), 'engine', 'pool', [$first, TestFactory::player('p2')], 'p1');

        self::assertSame(5, $duel->players[0]->monsterZones->occupiedCount());
        self::assertSame($occupied, $duel->players[0]->monsterZones);

        $invalid = $first->with(['monsterZones' => MonsterZones::empty(4)]);
        $snapshot = $invalid->toArray();
        try {
            createInitialDuelState('duel', gxLegacyProfile(), 'engine', 'pool', [$invalid, TestFactory::player('p2')], 'p1');
            self::fail('Capacidade incompatível foi aceita.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('As zonas do jogador são incompatíveis com o perfil.', $exception->getMessage());
            self::assertSame($snapshot, $invalid->toArray());
        }
    }

    public function test_engine_keeps_spell_trap_capacity_validation_and_field_zone_unchanged(): void
    {
        $player = TestFactory::player('p1')->with([
            'spellTrapZones' => SpellTrapZones::empty(0),
            'fieldZone' => 'legacy-field-id',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('As zonas do jogador são incompatíveis com o perfil.');
        createInitialDuelState('duel', gxLegacyProfile(), 'engine', 'pool', [$player, TestFactory::player('p2')], 'p1');
    }

    /** @return iterable<string, array{string}> */
    public static function globalDuplicateAreas(): iterable
    {
        yield 'PlayerCardZones e MonsterZones' => ['card-zones'];
        yield 'MonsterZones e spellTrapZones' => ['spell-trap'];
        yield 'MonsterZones e fieldZone' => ['field'];
        yield 'MonsterZones de jogadores diferentes' => ['other-player'];
    }

    #[DataProvider('globalDuplicateAreas')]
    public function test_global_uniqueness_crosses_every_monster_zone_boundary(string $area): void
    {
        $duplicate = 'DUPLICATE';
        $first = TestFactory::player('p1');
        $second = TestFactory::player('p2');

        if ($area === 'card-zones') {
            $duplicate = $first->cardZones->mainDeck->cards()[0]->id->value;
            $first = $first->with(['monsterZones' => TestFactory::monsterZones([TestFactory::card($duplicate), null, null, null, null])]);
        } elseif ($area === 'spell-trap') {
            $first = $first->with([
                'monsterZones' => TestFactory::monsterZones([TestFactory::card($duplicate), null, null, null, null]),
                'spellTrapZones' => TestFactory::spellTrapZones([null, TestFactory::card($duplicate), null, null, null]),
            ]);
        } elseif ($area === 'field') {
            $first = $first->with([
                'monsterZones' => TestFactory::monsterZones([null, TestFactory::card($duplicate), null, null, null]),
                'fieldZone' => $duplicate,
            ]);
        } else {
            $first = $first->with(['monsterZones' => TestFactory::monsterZones([TestFactory::card($duplicate), null, null, null, null])]);
            $second = $second->with(['monsterZones' => TestFactory::monsterZones([null, null, TestFactory::card($duplicate), null, null])]);
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

    public function test_internal_duplicate_is_rejected_by_aggregate_boundary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CardInstanceId duplicado nas Zonas de Monstro: DUPLICATE.');

        TestFactory::monsterZones([
            TestFactory::card('DUPLICATE'),
            null,
            TestFactory::card('DUPLICATE'),
        ]);
    }

    public function test_global_uniqueness_preserves_case_spaces_numeric_strings_and_unicode_exactly(): void
    {
        $ids = ['CARD', 'card', ' CARD', 'CARD ', '1', '01', 'café', "cafe\u{0301}"];
        $first = TestFactory::player('p1')->with([
            'monsterZones' => TestFactory::monsterZones(array_map(
                static fn (string $id): CardInstance => TestFactory::card($id),
                array_slice($ids, 0, 4),
            )),
        ]);
        $second = TestFactory::player('p2')->with([
            'monsterZones' => TestFactory::monsterZones(array_map(
                static fn (string $id): CardInstance => TestFactory::card($id),
                array_slice($ids, 4),
            )),
        ]);
        $profile = TestFactory::profile(['mainMonsterZones' => 4]);
        $duel = createInitialDuelState('duel', $profile, 'engine', 'pool', [$first->with([
            'spellTrapZones' => SpellTrapZones::empty(5),
        ]), $second], 'p1');

        self::assertSame(array_slice($ids, 0, 4), $duel->players[0]->toArray()['monsterZones']);
        self::assertSame(array_slice($ids, 4), $duel->players[1]->toArray()['monsterZones']);
    }

    public function test_engine_clones_player_but_structurally_shares_both_immutable_zone_aggregates(): void
    {
        $first = TestFactory::richPlayer('player-1');
        $second = TestFactory::richPlayer('player-2');
        $duel = createInitialDuelState('duel', gxLegacyProfile(), 'engine', 'pool', [$first, $second], 'player-1');

        self::assertNotSame($first, $duel->players[0]);
        self::assertNotSame($second, $duel->players[1]);
        self::assertSame($first->cardZones, $duel->players[0]->cardZones);
        self::assertSame($first->monsterZones, $duel->players[0]->monsterZones);
        self::assertSame($second->cardZones, $duel->players[1]->cardZones);
        self::assertSame($second->monsterZones, $duel->players[1]->monsterZones);
        self::assertSame($first->monsterZones->get(1), $duel->players[0]->monsterZones->get(1));
        self::assertSame($first->monsterZones->get(1)?->definition, $duel->players[0]->monsterZones->get(1)?->definition);
        self::assertSame($first->spellTrapZones, $duel->players[0]->spellTrapZones);
    }

    public function test_public_clone_operation_shares_monster_zones_for_both_players(): void
    {
        $duel = TestFactory::activeDuel(DuelPhase::STANDBY, 2);
        $result = processStandbyPhase($duel, gxLegacyProfile());

        foreach ([0, 1] as $index) {
            self::assertNotSame($duel->players[$index], $result->players[$index]);
            self::assertSame($duel->players[$index]->cardZones, $result->players[$index]->cardZones);
            self::assertSame($duel->players[$index]->monsterZones, $result->players[$index]->monsterZones);
            self::assertSame(
                $duel->players[$index]->monsterZones->get(1),
                $result->players[$index]->monsterZones->get(1),
            );
        }
    }
}
