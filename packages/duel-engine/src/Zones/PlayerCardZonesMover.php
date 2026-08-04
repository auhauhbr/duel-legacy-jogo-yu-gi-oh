<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Zones;

use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Identifiers\CardInstanceId;
use InvalidArgumentException;

final readonly class PlayerCardZonesMover
{
    public function move(
        PlayerCardZones $zones,
        CardInstanceId $cardId,
        CardLocation $source,
        CardLocation $destination,
        int $destinationIndex,
    ): PlayerCardZones {
        $sourceZone = $zones->get($source);
        $destinationZone = $zones->get($destination);

        if ($source === $destination) {
            throw new InvalidArgumentException(
                "A origem e o destino da movimentação devem ser diferentes: {$source->value}.",
            );
        }

        $card = $sourceZone->find($cardId);
        if ($card === null) {
            throw new InvalidArgumentException(
                "CardInstanceId {$cardId->value} não foi encontrado na localização de origem {$source->value}.",
            );
        }

        $destinationCount = $destinationZone->count();
        if ($destinationIndex < 0 || $destinationIndex > $destinationCount) {
            throw new InvalidArgumentException(
                "Índice de destino inválido para {$destination->value}: {$destinationIndex}; intervalo permitido de 0 a {$destinationCount}.",
            );
        }

        $sourceCards = array_values(array_filter(
            $sourceZone->cards(),
            static fn (CardInstance $sourceCard): bool => $sourceCard->id->value !== $cardId->value,
        ));
        $destinationCards = $destinationZone->cards();
        array_splice($destinationCards, $destinationIndex, 0, [$card]);

        $newSource = new OrderedCardZone($source, $sourceCards);
        $newDestination = new OrderedCardZone($destination, $destinationCards);

        return new PlayerCardZones(
            mainDeck: self::replacement(CardLocation::MAIN_DECK, $zones->mainDeck, $source, $newSource, $destination, $newDestination),
            hand: self::replacement(CardLocation::HAND, $zones->hand, $source, $newSource, $destination, $newDestination),
            graveyard: self::replacement(CardLocation::GRAVEYARD, $zones->graveyard, $source, $newSource, $destination, $newDestination),
            banishedFaceUp: self::replacement(CardLocation::BANISHED_FACE_UP, $zones->banishedFaceUp, $source, $newSource, $destination, $newDestination),
            banishedFaceDown: self::replacement(CardLocation::BANISHED_FACE_DOWN, $zones->banishedFaceDown, $source, $newSource, $destination, $newDestination),
            extraDeckFaceDown: self::replacement(CardLocation::EXTRA_DECK_FACE_DOWN, $zones->extraDeckFaceDown, $source, $newSource, $destination, $newDestination),
            extraDeckFaceUp: self::replacement(CardLocation::EXTRA_DECK_FACE_UP, $zones->extraDeckFaceUp, $source, $newSource, $destination, $newDestination),
        );
    }

    private static function replacement(
        CardLocation $location,
        OrderedCardZone $original,
        CardLocation $source,
        OrderedCardZone $newSource,
        CardLocation $destination,
        OrderedCardZone $newDestination,
    ): OrderedCardZone {
        return match ($location) {
            $source => $newSource,
            $destination => $newDestination,
            default => $original,
        };
    }
}
