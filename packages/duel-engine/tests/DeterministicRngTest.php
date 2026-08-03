<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Random\DeterministicRngState;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\createDeterministicRng;
use function DuelLegacy\DuelEngine\nextRandomFloat;
use function DuelLegacy\DuelEngine\nextRandomInt;
use function DuelLegacy\DuelEngine\nextRandomUint32;
use function DuelLegacy\DuelEngine\shuffleDeterministically;

final class DeterministicRngTest extends TestCase
{
    public function test_same_seed_produces_same_sequence_without_mutating_input(): void
    {
        $initial = createDeterministicRng('replay-seed');
        $snapshot = $initial->toArray();
        $first = nextRandomUint32($initial);
        $second = nextRandomUint32($first->nextState);
        self::assertSame($snapshot, $initial->toArray());
        self::assertSame(0, $initial->calls);
        self::assertSame(1, $first->nextState->calls);
        self::assertSame(2, $second->nextState->calls);
        self::assertSame($first->toArray(), nextRandomUint32(createDeterministicRng('replay-seed'))->toArray());
    }

    public function test_uint32_and_float_ranges(): void
    {
        $state = createDeterministicRng('ranges');
        for ($index = 0; $index < 500; $index++) {
            $uint = nextRandomUint32($state);
            self::assertGreaterThanOrEqual(0, $uint->value);
            self::assertLessThanOrEqual(0xFFFFFFFF, $uint->value);
            $float = nextRandomFloat($state);
            self::assertGreaterThanOrEqual(0.0, $float->value);
            self::assertLessThan(1.0, $float->value);
            $state = $uint->nextState;
        }
    }

    /** @return iterable<array{int|float, int|float}> */
    public static function validIntervals(): iterable
    {
        yield [-10, -2];
        yield [0, 2];
        yield [10, 1000];
        yield [-2_000_000_000, 2_000_000_000];
        yield [0, 4294967296];
        yield [42, 43];
    }

    #[DataProvider('validIntervals')]
    public function test_integer_ranges(int|float $min, int|float $max): void
    {
        $state = createDeterministicRng("{$min}:{$max}");
        for ($index = 0; $index < 100; $index++) {
            $result = nextRandomInt($state, $min, $max);
            self::assertGreaterThanOrEqual($min, $result->value);
            self::assertLessThan($max, $result->value);
            $state = $result->nextState;
        }
    }

    /** @return iterable<array{int|float, int|float, string}> */
    public static function invalidIntervals(): iterable
    {
        yield [0.5, 2, 'Os limites devem ser inteiros seguros.'];
        yield [0, 2.5, 'Os limites devem ser inteiros seguros.'];
        yield [NAN, 2, 'Os limites devem ser inteiros seguros.'];
        yield [0, NAN, 'Os limites devem ser inteiros seguros.'];
        yield [INF, 2, 'Os limites devem ser inteiros seguros.'];
        yield [0, -INF, 'Os limites devem ser inteiros seguros.'];
        yield [9_007_199_254_740_992, 9_007_199_254_740_993, 'Os limites devem ser inteiros seguros.'];
        yield [-9_007_199_254_740_993, -9_007_199_254_740_992, 'Os limites devem ser inteiros seguros.'];
        yield [0, 0, 'minInclusive deve ser menor que maxExclusive.'];
        yield [10, 5, 'minInclusive deve ser menor que maxExclusive.'];
        yield [0, 4294967297, 'O intervalo não pode exceder o espaço uint32.'];
    }

    #[DataProvider('invalidIntervals')]
    public function test_rejects_invalid_intervals(int|float $min, int|float $max, string $message): void
    {
        $state = createDeterministicRng('invalid');
        $snapshot = $state->toArray();
        try {
            nextRandomInt($state, $min, $max);
            self::fail();
        } catch (InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertSame($snapshot, $state->toArray());
        }
    }

    public function test_interval_of_size_one_returns_the_only_value_and_consumes_once(): void
    {
        $state = createDeterministicRng('single-value');
        foreach ([-9_007_199_254_740_991, -1, 0, 1, 9_007_199_254_740_990] as $minimum) {
            $result = nextRandomInt($state, $minimum, $minimum + 1);
            self::assertSame($minimum, $result->value);
            self::assertSame($state->calls + 1, $result->nextState->calls);
            $state = $result->nextState;
        }
    }

    public function test_php_rejects_incompatible_limit_types_before_domain_validation(): void
    {
        $function = new \ReflectionFunction('DuelLegacy\\DuelEngine\\nextRandomInt');
        $this->expectException(\TypeError::class);
        $function->invoke(createDeterministicRng('typed'), 'not-a-number', 10);
    }

    public function test_serialization_reconstruction_and_sequence_continuation_are_exact(): void
    {
        $state = createDeterministicRng('resume-sequence');
        for ($index = 0; $index < 7; $index++) {
            $state = nextRandomUint32($state)->nextState;
        }

        $serialized = $state->toArray();
        $reconstructed = new DeterministicRngState($serialized['seed'], $serialized['state'], $serialized['calls']);
        self::assertNotSame($state, $reconstructed);
        self::assertSame(nextRandomUint32($state)->toArray(), nextRandomUint32($reconstructed)->toArray());

        $firstShuffle = shuffleDeterministically(range(1, 12), $state);
        $shuffleState = $firstShuffle->nextState->toArray();
        $resumed = new DeterministicRngState($shuffleState['seed'], $shuffleState['state'], $shuffleState['calls']);
        self::assertSame(
            shuffleDeterministically(range('A', 'J'), $firstShuffle->nextState)->toArray(),
            shuffleDeterministically(range('A', 'J'), $resumed)->toArray(),
        );
    }

    public function test_historical_fnv_zero_seed_uses_fallback_without_consuming_rng(): void
    {
        // Encontrada por busca meet-in-the-middle sobre dois code units UTF-16.
        $state = createDeterministicRng("\u{24CC}\u{C431}");
        self::assertSame(
            ['seed' => "\u{24CC}\u{C431}", 'state' => 0x9E3779B9, 'calls' => 0],
            $state->toArray(),
        );

        $first = nextRandomUint32($state);
        self::assertSame(1359758873, $first->value);
        self::assertSame(
            ['seed' => "\u{24CC}\u{C431}", 'state' => 1359758873, 'calls' => 1],
            $first->nextState->toArray(),
        );
        self::assertSame(0, $state->calls);
    }
}
