<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Zones;

use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Identifiers\CardInstanceId;
use InvalidArgumentException;

final readonly class MonsterZones
{
    /**
     * @param  list<?CardInstance>  $slots
     */
    public function __construct(
        private array $slots,
    ) {
        self::assertSlots($slots);
    }

    public static function empty(int $capacity): self
    {
        if ($capacity < 0) {
            throw new InvalidArgumentException(
                "A capacidade das Zonas de Monstro não pode ser negativa: {$capacity}.",
            );
        }

        return new self(array_fill(0, $capacity, null));
    }

    /** @return list<?CardInstance> */
    public function slots(): array
    {
        return [...$this->slots];
    }

    public function capacity(): int
    {
        return count($this->slots);
    }

    public function occupiedCount(): int
    {
        return count(array_filter(
            $this->slots,
            static fn (?CardInstance $card): bool => $card !== null,
        ));
    }

    public function isEmpty(): bool
    {
        return $this->occupiedCount() === 0;
    }

    public function get(int $index): ?CardInstance
    {
        $this->assertValidIndex($index);

        return $this->slots[$index];
    }

    public function contains(CardInstanceId $id): bool
    {
        return $this->indexOf($id) !== null;
    }

    public function find(CardInstanceId $id): ?CardInstance
    {
        $index = $this->indexOf($id);

        return $index === null ? null : $this->slots[$index];
    }

    public function indexOf(CardInstanceId $id): ?int
    {
        foreach ($this->slots as $index => $card) {
            if ($card?->id->value === $id->value) {
                return $index;
            }
        }

        return null;
    }

    public function withSlot(int $index, ?CardInstance $card): self
    {
        $this->assertValidIndex($index);

        if ($this->slots[$index] === $card) {
            return $this;
        }

        $slots = [];
        foreach ($this->slots as $slotIndex => $currentCard) {
            $slots[] = $slotIndex === $index ? $card : $currentCard;
        }

        return new self($slots);
    }

    /**
     * @return list<?array{
     *     id: string,
     *     definition: array<string, int|string>
     * }>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (?CardInstance $card): ?array => $card?->toArray(),
            $this->slots,
        );
    }

    private function assertValidIndex(int $index): void
    {
        if ($index < 0 || $index >= $this->capacity()) {
            throw new InvalidArgumentException(
                "Índice das Zonas de Monstro fora do intervalo: {$index}.",
            );
        }
    }

    /** @param array<array-key, mixed> $slots */
    private static function assertSlots(array $slots): void
    {
        if (! array_is_list($slots)) {
            throw new InvalidArgumentException('slots deve ser uma lista.');
        }

        $seenIds = [];
        foreach ($slots as $card) {
            if ($card !== null && ! $card instanceof CardInstance) {
                throw new InvalidArgumentException('slots deve conter apenas CardInstance ou null.');
            }

            if ($card === null) {
                continue;
            }

            $key = "id:{$card->id->value}";
            if (isset($seenIds[$key])) {
                throw new InvalidArgumentException(
                    "CardInstanceId duplicado nas Zonas de Monstro: {$card->id->value}.",
                );
            }

            $seenIds[$key] = true;
        }
    }
}
