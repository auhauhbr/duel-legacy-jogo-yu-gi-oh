<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Players\DuelPlayerState;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use DuelLegacy\DuelEngine\Zones\MonsterZones;
use DuelLegacy\DuelEngine\Zones\OrderedCardZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

use function DuelLegacy\DuelEngine\drawCardsFromMainDeck;
use function DuelLegacy\DuelEngine\gxLegacyProfile;
use function DuelLegacy\DuelEngine\processDrawPhase;

final class AuthoritativePlayerCardZonesTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function legacyZoneKeys(): iterable
    {
        foreach (['mainDeck', 'hand', 'graveyard', 'banishedFaceUp', 'banishedFaceDown', 'extraDeckFaceDown', 'extraDeckFaceUp'] as $key) {
            yield $key => [$key];
        }
    }

    /** @return iterable<string, array{CardLocation}> */
    public static function offFieldLocations(): iterable
    {
        yield 'Deck Principal' => [CardLocation::MAIN_DECK];
        yield 'mão' => [CardLocation::HAND];
        yield 'Cemitério' => [CardLocation::GRAVEYARD];
        yield 'banida face-up' => [CardLocation::BANISHED_FACE_UP];
        yield 'banida face-down' => [CardLocation::BANISHED_FACE_DOWN];
        yield 'Deck Adicional face-down' => [CardLocation::EXTRA_DECK_FACE_DOWN];
        yield 'Deck Adicional face-up' => [CardLocation::EXTRA_DECK_FACE_UP];
    }

    /** @return iterable<string, array{CardLocation}> */
    public static function fieldLocations(): iterable
    {
        yield 'Monstro' => [CardLocation::MONSTER_ZONE];
        yield 'Magia/Armadilha' => [CardLocation::SPELL_TRAP_ZONE];
        yield 'Campo' => [CardLocation::FIELD_ZONE];
    }

    public function test_player_has_one_authoritative_off_field_state_and_preserves_object_identity(): void
    {
        $zones = TestFactory::playerCardZones(mainDeck: [$card = TestFactory::card('A')]);
        $player = new DuelPlayerState('p1', 8000, $zones, MonsterZones::empty(5), array_fill(0, 5, null), null, 0, 1);
        $reflection = new ReflectionClass($player);

        self::assertTrue($reflection->isReadOnly());
        self::assertSame(
            ['playerId', 'lifePoints', 'cardZones', 'monsterZones', 'spellTrapZones', 'fieldZone', 'normalSummonsUsed', 'normalSummonLimit'],
            array_map(static fn (ReflectionProperty $property): string => $property->getName(), $reflection->getProperties()),
        );
        self::assertSame($zones, $player->cardZones);
        self::assertSame($card, $player->cardZones->mainDeck->cards()[0]);
        self::assertSame($card->definition, $player->cardZones->mainDeck->cards()[0]->definition);
    }

    public function test_with_shares_card_zones_unless_replaced(): void
    {
        $player = TestFactory::richPlayer('player-1');
        $replacement = TestFactory::playerCardZones(mainDeck: TestFactory::cards(['replacement']));

        self::assertSame($player->cardZones, $player->with(['lifePoints' => 1])->cardZones);
        self::assertSame($player->cardZones, $player->with(['normalSummonsUsed' => 2, 'normalSummonLimit' => 3])->cardZones);
        self::assertSame($replacement, $player->with(['cardZones' => $replacement])->cardZones);
        self::assertSame(7100, $player->lifePoints);
        self::assertSame('player-1-main-1', $player->cardZones->mainDeck->cards()[0]->id->value);
    }

    #[DataProvider('legacyZoneKeys')]
    public function test_with_rejects_every_legacy_zone_key_with_exact_message(string $key): void
    {
        $player = TestFactory::player('p1');
        $snapshot = $player->toArray();

        try {
            $player->with([$key => []]);
            self::fail('Chave legada foi aceita.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('As zonas de cartas fora do campo devem ser alteradas por cardZones.', $exception->getMessage());
            self::assertSame($snapshot, $player->toArray());
        }
    }

    public function test_serialization_keeps_historical_ids_key_order_and_omits_definitions(): void
    {
        $player = TestFactory::richPlayer('player-1');
        $serialized = $player->toArray();

        self::assertSame(
            ['playerId', 'lifePoints', 'mainDeck', 'hand', 'graveyard', 'banishedFaceUp', 'banishedFaceDown', 'extraDeckFaceDown', 'extraDeckFaceUp', 'monsterZones', 'spellTrapZones', 'fieldZone', 'normalSummonsUsed', 'normalSummonLimit'],
            array_keys($serialized),
        );
        self::assertSame(['player-1-main-1', 'player-1-main-2'], array_slice($serialized['mainDeck'], 0, 2));
        self::assertSame(['player-1-hand'], $serialized['hand']);
        self::assertStringNotContainsString('definition', json_encode($serialized, JSON_THROW_ON_ERROR));
        self::assertSame([], TestFactory::playerCardZones()->hand->cards());
    }

    #[DataProvider('offFieldLocations')]
    public function test_with_zone_replaces_only_the_selected_zone_and_shares_the_other_six(CardLocation $location): void
    {
        $zones = TestFactory::playerCardZones(
            mainDeck: TestFactory::cards(['main']),
            hand: TestFactory::cards(['hand']),
            graveyard: TestFactory::cards(['grave']),
            banishedFaceUp: TestFactory::cards(['up']),
            banishedFaceDown: TestFactory::cards(['down']),
            extraDeckFaceDown: TestFactory::cards(['extra-down']),
            extraDeckFaceUp: TestFactory::cards(['extra-up']),
        );
        $replacementCard = TestFactory::card("replacement-{$location->value}");
        $replacement = TestFactory::zone($location, [$replacementCard]);
        $result = $zones->withZone($replacement);

        self::assertNotSame($zones, $result);
        self::assertSame($replacement, $result->get($location));
        self::assertSame($replacementCard, $result->get($location)->cards()[0]);
        self::assertSame($replacementCard->definition, $result->get($location)->cards()[0]->definition);
        foreach (self::offFieldLocations() as [$otherLocation]) {
            if ($otherLocation !== $location) {
                self::assertSame($zones->get($otherLocation), $result->get($otherLocation));
            }
        }
    }

    public function test_with_zone_returns_same_aggregate_for_same_reference_and_revalidates_duplicates(): void
    {
        $zones = TestFactory::playerCardZones(mainDeck: TestFactory::cards(['same']));
        self::assertSame($zones, $zones->withZone($zones->mainDeck));

        $this->expectExceptionMessage('CardInstanceId duplicado entre MAIN_DECK e HAND: same.');
        $zones->withZone(TestFactory::zone(CardLocation::HAND, TestFactory::cards(['same'])));
    }

    #[DataProvider('fieldLocations')]
    public function test_with_zone_rejects_field_locations_with_public_message(CardLocation $location): void
    {
        $zone = (new ReflectionClass(OrderedCardZone::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($zone, 'location'))->setValue($zone, $location);
        (new ReflectionProperty($zone, 'cards'))->setValue($zone, []);

        $this->expectExceptionMessage("A localização {$location->value} não pertence às zonas de cartas do jogador.");
        TestFactory::playerCardZones()->withZone($zone);
    }

    public function test_draw_moves_original_instances_and_zero_draw_keeps_zones_shared(): void
    {
        $player = TestFactory::player('p1', 3);
        $top = $player->cardZones->mainDeck->cards()[0];
        $zero = drawCardsFromMainDeck($player, 0);
        $drawn = drawCardsFromMainDeck($player, 1);

        self::assertNotSame($player, $zero->playerState);
        self::assertSame($player->cardZones, $zero->playerState->cardZones);
        self::assertSame([$top->id->value], $drawn->drawnCardIds);
        self::assertSame($top, $drawn->playerState->cardZones->hand->cards()[0]);
        self::assertSame($top->definition, $drawn->playerState->cardZones->hand->cards()[0]->definition);
        self::assertSame(3, $player->cardZones->mainDeck->count());
    }

    public function test_draw_phase_shares_non_current_players_immutable_zones(): void
    {
        $duel = TestFactory::activeDuel(DuelPhase::DRAW, 2);
        $result = processDrawPhase($duel, gxLegacyProfile());

        self::assertSame($duel->players[0]->cardZones, $result->players[0]->cardZones);
        self::assertNotSame($duel->players[1]->cardZones, $result->players[1]->cardZones);
    }

    public function test_transitional_projector_is_not_autoloadable_or_present(): void
    {
        $className = 'DuelLegacy\\DuelEngine\\Players\\PlayerCardZones'.'Projector';
        self::assertFileDoesNotExist(__DIR__.'/../src/Players/PlayerCardZones'.'Projector.php');
        self::assertFalse(class_exists($className));
    }
}
