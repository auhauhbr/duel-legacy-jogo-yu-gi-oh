<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Rules\SummonMethod;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\gxLegacyProfile;
use function DuelLegacy\DuelEngine\validateRulesProfile;

final class RulesProfileTest extends TestCase
{
    public function test_gx_legacy_matches_documented_values(): void
    {
        $profile = gxLegacyProfile();
        self::assertSame('GX_LEGACY', $profile->id);
        self::assertSame(8000, $profile->startingLifePoints);
        self::assertSame(5, $profile->startingHandSize);
        self::assertSame(6, $profile->handLimit);
        self::assertSame([40, 60, 15, 0], [$profile->mainDeckMin, $profile->mainDeckMax, $profile->extraDeckMax, $profile->sideDeckMax]);
        self::assertSame([5, 5], [$profile->mainMonsterZones, $profile->spellTrapZones]);
        self::assertFalse($profile->drawOnFirstTurn);
        self::assertFalse($profile->battleOnFirstTurn);
        self::assertSame(SummonMethod::cases(), $profile->enabledSummons);
        self::assertTrue(validateRulesProfile($profile)->valid);
        self::assertSame([], validateRulesProfile($profile)->errors);
    }

    /** @return iterable<string, array{array<string, mixed>, array{code: string, field: string}}> */
    public static function invalidProfiles(): iterable
    {
        yield 'empty id' => [['id' => '  '], ['code' => 'EMPTY_ID', 'field' => 'id']];
        yield 'zero life' => [['startingLifePoints' => 0], ['code' => 'INVALID_STARTING_LIFE_POINTS', 'field' => 'startingLifePoints']];
        yield 'negative hand' => [['startingHandSize' => -1], ['code' => 'NEGATIVE_QUANTITY', 'field' => 'startingHandSize']];
        yield 'negative hand limit' => [['handLimit' => -1], ['code' => 'NEGATIVE_QUANTITY', 'field' => 'handLimit']];
        yield 'negative deck min' => [['mainDeckMin' => -1], ['code' => 'NEGATIVE_QUANTITY', 'field' => 'mainDeckMin']];
        yield 'negative deck max' => [['mainDeckMax' => -1], ['code' => 'NEGATIVE_QUANTITY', 'field' => 'mainDeckMax']];
        yield 'negative extra deck max' => [['extraDeckMax' => -1], ['code' => 'NEGATIVE_QUANTITY', 'field' => 'extraDeckMax']];
        yield 'negative side deck max' => [['sideDeckMax' => -1], ['code' => 'NEGATIVE_QUANTITY', 'field' => 'sideDeckMax']];
        yield 'negative monster zones' => [['mainMonsterZones' => -1], ['code' => 'NEGATIVE_QUANTITY', 'field' => 'mainMonsterZones']];
        yield 'negative spell/trap zones' => [['spellTrapZones' => -1], ['code' => 'NEGATIVE_QUANTITY', 'field' => 'spellTrapZones']];
        yield 'hand limit' => [['handLimit' => 4], ['code' => 'HAND_LIMIT_BELOW_STARTING_HAND_SIZE', 'field' => 'handLimit']];
        yield 'deck min' => [['mainDeckMin' => 61], ['code' => 'MAIN_DECK_MIN_ABOVE_MAX', 'field' => 'mainDeckMin']];
        yield 'no summons' => [['enabledSummons' => []], ['code' => 'NO_ENABLED_SUMMONS', 'field' => 'enabledSummons']];
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array{code: string, field: string}  $expectedError
     */
    #[DataProvider('invalidProfiles')]
    public function test_rejects_invalid_profiles(array $changes, array $expectedError): void
    {
        $profile = TestFactory::profile($changes);
        $snapshot = $profile->toArray();
        $result = validateRulesProfile($profile);
        self::assertFalse($result->valid);
        self::assertContains($expectedError, $result->toArray()['errors']);
        self::assertSame($snapshot, $profile->toArray());
    }

    public function test_reports_duplicate_summon_method(): void
    {
        $result = validateRulesProfile(TestFactory::profile(['enabledSummons' => [SummonMethod::NORMAL, SummonMethod::NORMAL]]));
        self::assertContains(
            ['code' => 'DUPLICATE_SUMMON_METHOD', 'field' => 'enabledSummons', 'method' => 'NORMAL'],
            $result->toArray()['errors'],
        );
    }
}
