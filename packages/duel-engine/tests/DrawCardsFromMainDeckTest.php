<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\drawCardsFromMainDeck;

final class DrawCardsFromMainDeckTest extends TestCase
{
    public function test_draws_from_index_zero_and_appends_to_hand(): void
    {
        $player = TestFactory::player('p1', 4)->with(['mainDeck' => ['A', 'B', 'C', 'D'], 'hand' => ['X']]);
        $snapshot = $player->toArray();
        $result = drawCardsFromMainDeck($player, 3);
        self::assertSame(['A', 'B', 'C'], $result->drawnCardIds);
        self::assertSame(['D'], $result->playerState->mainDeck);
        self::assertSame(['X', 'A', 'B', 'C'], $result->playerState->hand);
        self::assertSame($snapshot, $player->toArray());
        self::assertNotSame($player, $result->playerState);
    }

    public function test_zero_and_full_deck_draws(): void
    {
        $player = TestFactory::player('p1', 2);
        self::assertSame($player->toArray(), drawCardsFromMainDeck($player, 0)->playerState->toArray());
        $result = drawCardsFromMainDeck($player, 2);
        self::assertSame([], $result->playerState->mainDeck);
        self::assertSame($player->mainDeck, $result->drawnCardIds);
    }

    /** @return iterable<array{int|float, string}> */
    public static function invalidAmounts(): iterable
    {
        yield [-1, 'A quantidade de compra não pode ser negativa.'];
        yield [1.5, 'A quantidade de compra deve ser um inteiro finito.'];
        yield [NAN, 'A quantidade de compra deve ser um inteiro finito.'];
        yield [INF, 'A quantidade de compra deve ser um inteiro finito.'];
        yield [-INF, 'A quantidade de compra deve ser um inteiro finito.'];
        yield [5, 'O Deck Principal não possui cartas suficientes.'];
    }

    #[DataProvider('invalidAmounts')]
    public function test_rejects_invalid_amounts(int|float $amount, string $message): void
    {
        $player = TestFactory::player('p1', 4);
        $snapshot = $player->toArray();
        try {
            drawCardsFromMainDeck($player, $amount);
            self::fail();
        } catch (InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertSame($snapshot, $player->toArray());
        }
    }

    public function test_empty_deck_rejects_positive_draw_without_mutation(): void
    {
        $player = TestFactory::player('p1', 1)->with(['mainDeck' => []]);
        $snapshot = $player->toArray();
        try {
            drawCardsFromMainDeck($player, 1);
            self::fail();
        } catch (InvalidArgumentException $exception) {
            self::assertSame('O Deck Principal não possui cartas suficientes.', $exception->getMessage());
            self::assertSame($snapshot, $player->toArray());
        }
    }

    public function test_php_rejects_incompatible_amount_type_before_domain_validation(): void
    {
        $function = new \ReflectionFunction('DuelLegacy\\DuelEngine\\drawCardsFromMainDeck');
        $this->expectException(\TypeError::class);
        $function->invoke(TestFactory::player('p1'), 'not-a-number');
    }

    public function test_preserves_every_other_area_and_two_runs_are_independent(): void
    {
        $player = TestFactory::richPlayer('player-1');
        $snapshot = $player->toArray();
        $first = drawCardsFromMainDeck($player, 2);
        $second = drawCardsFromMainDeck($player, 2);

        foreach (['lifePoints', 'graveyard', 'banishedFaceUp', 'banishedFaceDown', 'extraDeckFaceDown', 'extraDeckFaceUp', 'monsterZones', 'spellTrapZones', 'fieldZone', 'normalSummonsUsed', 'normalSummonLimit'] as $field) {
            self::assertSame($snapshot[$field], $first->playerState->toArray()[$field], $field);
        }
        self::assertSame($snapshot, $player->toArray());
        self::assertNotSame($player, $first->playerState);
        self::assertNotSame($first->playerState, $second->playerState);
        self::assertSame($first->playerState->toArray(), $second->playerState->toArray());
        self::assertSame($first->drawnCardIds, $second->drawnCardIds);

        $serialized = $first->playerState->toArray();
        $serialized['graveyard'][] = 'mutated-copy';
        self::assertSame($first->playerState->toArray(), $second->playerState->toArray());
    }
}
