<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Zones;

use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Identifiers\CardInstanceId;
use InvalidArgumentException;

final readonly class PlayerCardZones
{
    public function __construct(
        public OrderedCardZone $mainDeck,
        public OrderedCardZone $hand,
        public OrderedCardZone $graveyard,
        public OrderedCardZone $banishedFaceUp,
        public OrderedCardZone $banishedFaceDown,
        public OrderedCardZone $extraDeckFaceDown,
        public OrderedCardZone $extraDeckFaceUp,
    ) {
        self::assertLocation('mainDeck', CardLocation::MAIN_DECK, $mainDeck);
        self::assertLocation('hand', CardLocation::HAND, $hand);
        self::assertLocation('graveyard', CardLocation::GRAVEYARD, $graveyard);
        self::assertLocation('banishedFaceUp', CardLocation::BANISHED_FACE_UP, $banishedFaceUp);
        self::assertLocation('banishedFaceDown', CardLocation::BANISHED_FACE_DOWN, $banishedFaceDown);
        self::assertLocation('extraDeckFaceDown', CardLocation::EXTRA_DECK_FACE_DOWN, $extraDeckFaceDown);
        self::assertLocation('extraDeckFaceUp', CardLocation::EXTRA_DECK_FACE_UP, $extraDeckFaceUp);

        self::assertUniqueCardInstanceIds($this->zones());
    }

    /** @return list<OrderedCardZone> */
    public function zones(): array
    {
        return [
            $this->mainDeck,
            $this->hand,
            $this->graveyard,
            $this->banishedFaceUp,
            $this->banishedFaceDown,
            $this->extraDeckFaceDown,
            $this->extraDeckFaceUp,
        ];
    }

    public function get(CardLocation $location): OrderedCardZone
    {
        return match ($location) {
            CardLocation::MAIN_DECK => $this->mainDeck,
            CardLocation::HAND => $this->hand,
            CardLocation::GRAVEYARD => $this->graveyard,
            CardLocation::BANISHED_FACE_UP => $this->banishedFaceUp,
            CardLocation::BANISHED_FACE_DOWN => $this->banishedFaceDown,
            CardLocation::EXTRA_DECK_FACE_DOWN => $this->extraDeckFaceDown,
            CardLocation::EXTRA_DECK_FACE_UP => $this->extraDeckFaceUp,
            default => throw new InvalidArgumentException(
                "A localização {$location->value} não pertence às zonas de cartas do jogador.",
            ),
        };
    }

    public function contains(CardInstanceId $id): bool
    {
        return $this->find($id) !== null;
    }

    public function find(CardInstanceId $id): ?CardInstance
    {
        foreach ($this->zones() as $zone) {
            $card = $zone->find($id);
            if ($card !== null) {
                return $card;
            }
        }

        return null;
    }

    public function count(): int
    {
        return array_sum(array_map(
            static fn (OrderedCardZone $zone): int => $zone->count(),
            $this->zones(),
        ));
    }

    /** @return array<string, array{location: string, cards: list<array{id: string, definition: array<string, int|string>}>}> */
    public function toArray(): array
    {
        return [
            'mainDeck' => $this->mainDeck->toArray(),
            'hand' => $this->hand->toArray(),
            'graveyard' => $this->graveyard->toArray(),
            'banishedFaceUp' => $this->banishedFaceUp->toArray(),
            'banishedFaceDown' => $this->banishedFaceDown->toArray(),
            'extraDeckFaceDown' => $this->extraDeckFaceDown->toArray(),
            'extraDeckFaceUp' => $this->extraDeckFaceUp->toArray(),
        ];
    }

    private static function assertLocation(
        string $property,
        CardLocation $expected,
        OrderedCardZone $zone,
    ): void {
        if ($zone->location !== $expected) {
            throw new InvalidArgumentException(
                "A propriedade {$property} exige a localização {$expected->value}; recebida {$zone->location->value}.",
            );
        }
    }

    /** @param list<OrderedCardZone> $zones */
    private static function assertUniqueCardInstanceIds(array $zones): void
    {
        /** @var array<string, CardLocation> $firstLocations */
        $firstLocations = [];

        foreach ($zones as $zone) {
            foreach ($zone->cards() as $card) {
                $key = "id:{$card->id->value}";
                if (isset($firstLocations[$key])) {
                    throw new InvalidArgumentException(
                        "CardInstanceId duplicado entre {$firstLocations[$key]->value} e {$zone->location->value}: {$card->id->value}.",
                    );
                }

                $firstLocations[$key] = $zone->location;
            }
        }
    }
}
