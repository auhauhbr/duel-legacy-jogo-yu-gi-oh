<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Zones;

use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Identifiers\CardInstanceId;
use InvalidArgumentException;

final readonly class OrderedCardZone
{
    /**
     * @param  list<CardInstance>  $cards
     */
    public function __construct(
        public CardLocation $location,
        private array $cards = [],
    ) {
        if (! in_array($location, self::allowedLocations(), true)) {
            throw new InvalidArgumentException(
                "A localização {$location->value} não é aceita por uma zona ordenada fora do campo.",
            );
        }

        self::assertCards($cards);
    }

    /** @return list<CardInstance> */
    public function cards(): array
    {
        return [...$this->cards];
    }

    public function count(): int
    {
        return count($this->cards);
    }

    public function isEmpty(): bool
    {
        return $this->cards === [];
    }

    public function contains(CardInstanceId $id): bool
    {
        return $this->find($id) !== null;
    }

    public function find(CardInstanceId $id): ?CardInstance
    {
        foreach ($this->cards as $card) {
            if ($card->id->value === $id->value) {
                return $card;
            }
        }

        return null;
    }

    /** @return array{location: string, cards: list<array{id: string, definition: array<string, int|string>}>} */
    public function toArray(): array
    {
        return [
            'location' => $this->location->value,
            'cards' => array_map(
                static fn (CardInstance $card): array => $card->toArray(),
                $this->cards,
            ),
        ];
    }

    /** @return list<CardLocation> */
    private static function allowedLocations(): array
    {
        return [
            CardLocation::MAIN_DECK,
            CardLocation::HAND,
            CardLocation::GRAVEYARD,
            CardLocation::BANISHED_FACE_UP,
            CardLocation::BANISHED_FACE_DOWN,
            CardLocation::EXTRA_DECK_FACE_DOWN,
            CardLocation::EXTRA_DECK_FACE_UP,
        ];
    }

    /** @param array<array-key, mixed> $cards */
    private static function assertCards(array $cards): void
    {
        if (! array_is_list($cards)) {
            throw new InvalidArgumentException('cards deve ser uma lista.');
        }

        $seenIds = [];
        foreach ($cards as $card) {
            if (! $card instanceof CardInstance) {
                throw new InvalidArgumentException('cards deve conter apenas CardInstance.');
            }

            if (in_array($card->id->value, $seenIds, true)) {
                throw new InvalidArgumentException(
                    "CardInstanceId duplicado na zona ordenada fora do campo: {$card->id->value}.",
                );
            }

            $seenIds[] = $card->id->value;
        }
    }
}
