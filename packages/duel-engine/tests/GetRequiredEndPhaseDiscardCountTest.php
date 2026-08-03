<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\getRequiredEndPhaseDiscardCount;
use function DuelLegacy\DuelEngine\gxLegacyProfile;

final class GetRequiredEndPhaseDiscardCountTest extends TestCase
{
    /** @return iterable<array{int, int}> */
    public static function handSizes(): iterable
    {
        yield [0, 0];
        yield [4, 0];
        yield [6, 0];
        yield [7, 1];
        yield [10, 4];
    }

    #[DataProvider('handSizes')]
    public function test_calculates_only_current_players_excess_without_mutation(int $handSize, int $expected): void
    {
        $duel = TestFactory::endDuel();
        $players = $duel->players;
        $players[0] = $players[0]->with(['hand' => array_map(static fn (int $index): string => "hand-{$index}", range(1, $handSize))]);
        if ($handSize === 0) {
            $players[0] = $players[0]->with(['hand' => []]);
        }
        $players[1] = $players[1]->with(['hand' => range('a', 'z')]);
        $duel = $duel->with(['players' => $players]);
        $snapshot = $duel->toArray();
        self::assertSame($expected, getRequiredEndPhaseDiscardCount($duel, gxLegacyProfile()));
        self::assertSame($snapshot, $duel->toArray());
        self::assertSame($expected, getRequiredEndPhaseDiscardCount($duel, gxLegacyProfile()));
    }

    public function test_works_for_second_physical_player(): void
    {
        $duel = TestFactory::endDuel(2);
        $players = $duel->players;
        $players[1] = $players[1]->with(['hand' => range('a', 'i')]);
        self::assertSame(3, getRequiredEndPhaseDiscardCount($duel->with(['players' => $players]), gxLegacyProfile()));
    }

    public function test_rejects_wrong_phase_and_non_integer_hand_limit(): void
    {
        $duel = TestFactory::endDuel();
        try {
            getRequiredEndPhaseDiscardCount($duel->with(['phase' => DuelPhase::MAIN_1]), gxLegacyProfile());
            self::fail();
        } catch (\Throwable $exception) {
            self::assertSame('O Duelo deve estar na fase END.', $exception->getMessage());
        }
        foreach ([6.5, 7.25, -1] as $limit) {
            try {
                getRequiredEndPhaseDiscardCount($duel, TestFactory::profile(['handLimit' => $limit]));
                self::fail();
            } catch (\Throwable $exception) {
                self::assertSame('RulesProfile inválido.', $exception->getMessage());
            }
        }
    }
}
