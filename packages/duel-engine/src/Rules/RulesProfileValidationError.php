<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Rules;

use JsonSerializable;

final readonly class RulesProfileValidationError implements JsonSerializable
{
    private function __construct(
        public RulesProfileValidationErrorCode $code,
        public RulesProfileValidationField $field,
        public ?SummonMethod $method = null,
    ) {}

    public static function emptyId(): self
    {
        return new self(RulesProfileValidationErrorCode::EMPTY_ID, RulesProfileValidationField::ID);
    }

    public static function invalidStartingLifePoints(): self
    {
        return new self(
            RulesProfileValidationErrorCode::INVALID_STARTING_LIFE_POINTS,
            RulesProfileValidationField::STARTING_LIFE_POINTS,
        );
    }

    public static function negativeQuantity(RulesProfileQuantityField $field): self
    {
        return new self(
            RulesProfileValidationErrorCode::NEGATIVE_QUANTITY,
            RulesProfileValidationField::from($field->value),
        );
    }

    public static function handLimitBelowStartingHandSize(): self
    {
        return new self(
            RulesProfileValidationErrorCode::HAND_LIMIT_BELOW_STARTING_HAND_SIZE,
            RulesProfileValidationField::HAND_LIMIT,
        );
    }

    public static function mainDeckMinAboveMax(): self
    {
        return new self(
            RulesProfileValidationErrorCode::MAIN_DECK_MIN_ABOVE_MAX,
            RulesProfileValidationField::MAIN_DECK_MIN,
        );
    }

    public static function noEnabledSummons(): self
    {
        return new self(
            RulesProfileValidationErrorCode::NO_ENABLED_SUMMONS,
            RulesProfileValidationField::ENABLED_SUMMONS,
        );
    }

    public static function duplicateSummonMethod(SummonMethod $method): self
    {
        return new self(
            RulesProfileValidationErrorCode::DUPLICATE_SUMMON_METHOD,
            RulesProfileValidationField::ENABLED_SUMMONS,
            $method,
        );
    }

    /** @return array{code: string, field: string, method?: string} */
    public function toArray(): array
    {
        $serialized = ['code' => $this->code->value, 'field' => $this->field->value];
        if ($this->method !== null) {
            $serialized['method'] = $this->method->value;
        }

        return $serialized;
    }

    /** @return array{code: string, field: string, method?: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
