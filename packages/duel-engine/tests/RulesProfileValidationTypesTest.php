<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Rules\RulesProfile;
use DuelLegacy\DuelEngine\Rules\RulesProfileQuantityField;
use DuelLegacy\DuelEngine\Rules\RulesProfileValidationError;
use DuelLegacy\DuelEngine\Rules\RulesProfileValidationErrorCode;
use DuelLegacy\DuelEngine\Rules\RulesProfileValidationResult;
use DuelLegacy\DuelEngine\Rules\SummonMethod;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ValueError;

final class RulesProfileValidationTypesTest extends TestCase
{
    /** @return list<string> */
    private static function quantityFieldNames(): array
    {
        return [
            'startingHandSize',
            'handLimit',
            'mainDeckMin',
            'mainDeckMax',
            'extraDeckMax',
            'sideDeckMax',
            'mainMonsterZones',
            'spellTrapZones',
        ];
    }

    public function test_quantity_field_has_exactly_the_historical_values_and_real_properties(): void
    {
        $serialized = array_map(
            static fn (RulesProfileQuantityField $field): string => $field->value,
            RulesProfileQuantityField::cases(),
        );

        self::assertSame(self::quantityFieldNames(), $serialized);
        foreach (RulesProfileQuantityField::cases() as $field) {
            self::assertTrue(property_exists(RulesProfile::class, $field->value));
            self::assertSame($field, RulesProfileQuantityField::from($field->value));
        }
    }

    public function test_quantity_field_rejects_unknown_value(): void
    {
        $this->expectException(ValueError::class);
        RulesProfileQuantityField::from('inventedQuantity');
    }

    /** @return iterable<string, array{RulesProfileValidationError, array{code: string, field: string, method?: string}}> */
    public static function errors(): iterable
    {
        yield 'empty id' => [RulesProfileValidationError::emptyId(), ['code' => 'EMPTY_ID', 'field' => 'id']];
        yield 'life points' => [RulesProfileValidationError::invalidStartingLifePoints(), ['code' => 'INVALID_STARTING_LIFE_POINTS', 'field' => 'startingLifePoints']];
        yield 'negative quantity' => [RulesProfileValidationError::negativeQuantity(RulesProfileQuantityField::MAIN_DECK_MAX), ['code' => 'NEGATIVE_QUANTITY', 'field' => 'mainDeckMax']];
        yield 'hand relation' => [RulesProfileValidationError::handLimitBelowStartingHandSize(), ['code' => 'HAND_LIMIT_BELOW_STARTING_HAND_SIZE', 'field' => 'handLimit']];
        yield 'deck relation' => [RulesProfileValidationError::mainDeckMinAboveMax(), ['code' => 'MAIN_DECK_MIN_ABOVE_MAX', 'field' => 'mainDeckMin']];
        yield 'no summons' => [RulesProfileValidationError::noEnabledSummons(), ['code' => 'NO_ENABLED_SUMMONS', 'field' => 'enabledSummons']];
        yield 'duplicate summon' => [RulesProfileValidationError::duplicateSummonMethod(SummonMethod::FUSION), ['code' => 'DUPLICATE_SUMMON_METHOD', 'field' => 'enabledSummons', 'method' => 'FUSION']];
    }

    /** @param array{code: string, field: string, method?: string} $serialized */
    #[DataProvider('errors')]
    public function test_error_codes_fields_methods_and_serialization_are_exact(
        RulesProfileValidationError $error,
        array $serialized,
    ): void {
        self::assertSame($serialized, $error->toArray());
        self::assertSame($serialized, $error->jsonSerialize());
        self::assertSame(json_encode($serialized, JSON_THROW_ON_ERROR), json_encode($error, JSON_THROW_ON_ERROR));
        self::assertContains($error->code, RulesProfileValidationErrorCode::cases());
    }

    public function test_validation_result_keeps_a_typed_list_and_serializes_without_exposing_it(): void
    {
        $error = RulesProfileValidationError::emptyId();
        $result = new RulesProfileValidationResult(false, [$error]);
        $serialized = $result->toArray();
        $serialized['errors'][0]['field'] = 'changed-copy';

        self::assertSame([$error], $result->errors);
        self::assertSame('id', $result->toArray()['errors'][0]['field']);
        self::assertSame($result->toArray(), $result->jsonSerialize());
    }

    public function test_validation_result_rejects_an_untyped_error_list_at_runtime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('errors deve conter apenas RulesProfileValidationError.');

        $constructor = new \ReflectionClass(RulesProfileValidationResult::class);
        $constructor->newInstance(false, [['code' => 'EMPTY_ID', 'field' => 'id']]);
    }

    public function test_rules_profile_rejects_an_unknown_summon_method_at_runtime(): void
    {
        $profile = TestFactory::profile();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('enabledSummons deve conter apenas SummonMethod.');
        $profile->with(['enabledSummons' => ['INVALID']]);
    }

    public function test_decimal_quantities_keep_the_historical_number_semantics(): void
    {
        $profile = TestFactory::profile([
            'startingHandSize' => 5.5,
            'handLimit' => 6.5,
            'mainMonsterZones' => 5.5,
            'spellTrapZones' => 5.5,
        ]);

        self::assertTrue(\DuelLegacy\DuelEngine\validateRulesProfile($profile)->valid);
        self::assertSame(5.5, $profile->startingHandSize);
    }

    public function test_php_rejects_an_incompatible_numeric_type_at_construction(): void
    {
        $this->expectException(\TypeError::class);
        TestFactory::profile(['startingLifePoints' => 'not-a-number']);
    }
}
