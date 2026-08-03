<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Rules;

use InvalidArgumentException;
use JsonSerializable;

final readonly class RulesProfileValidationResult implements JsonSerializable
{
    /** @param list<RulesProfileValidationError> $errors */
    public function __construct(public bool $valid, public array $errors)
    {
        self::assertErrors($errors);
    }

    /** @param array<array-key, mixed> $errors */
    private static function assertErrors(array $errors): void
    {
        foreach ($errors as $error) {
            if (! $error instanceof RulesProfileValidationError) {
                throw new InvalidArgumentException('errors deve conter apenas RulesProfileValidationError.');
            }
        }
    }

    /** @return array{valid: bool, errors: list<array{code: string, field: string, method?: string}>} */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => array_map(
                static fn (RulesProfileValidationError $error): array => $error->toArray(),
                $this->errors,
            ),
        ];
    }

    /** @return array{valid: bool, errors: list<array{code: string, field: string, method?: string}>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
