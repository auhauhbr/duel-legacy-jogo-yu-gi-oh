<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\createDeterministicRng;
use function DuelLegacy\DuelEngine\shuffleDeterministically;

final class ShuffleDeterministicallyTest extends TestCase
{
    /** @return iterable<array{int}> */
    public static function lengths(): iterable
    {
        foreach ([0, 1, 2, 5, 20] as $length) {
            yield [$length];
        }
    }

    #[DataProvider('lengths')]
    public function test_consumes_exactly_length_minus_one_calls(int $length): void
    {
        $input = range(0, max(0, $length - 1));
        if ($length === 0) {
            $input = [];
        }
        $rng = createDeterministicRng("calls-{$length}");
        $result = shuffleDeterministically($input, $rng);
        self::assertSame(max(0, $length - 1), $result->nextState->calls);
        self::assertCount($length, $result->items);
    }

    public function test_preserves_input_elements_duplicates_and_object_references(): void
    {
        $first = (object) ['id' => 1];
        $second = (object) ['id' => 2];
        $input = [$first, $second, $first];
        $snapshot = $input;
        $result = shuffleDeterministically($input, createDeterministicRng('objects'));
        self::assertSame($snapshot, $input);
        self::assertCount(2, array_filter($result->items, static fn (object $item): bool => $item === $first));
        self::assertTrue(in_array($second, $result->items, true));
    }

    public function test_same_seed_replays_and_different_seeds_differ(): void
    {
        $items = range(1, 10);
        $first = shuffleDeterministically($items, createDeterministicRng('same'));
        $second = shuffleDeterministically($items, createDeterministicRng('same'));
        self::assertSame($first->toArray(), $second->toArray());
        self::assertNotSame($first->items, shuffleDeterministically($items, createDeterministicRng('different'))->items);
    }
}
