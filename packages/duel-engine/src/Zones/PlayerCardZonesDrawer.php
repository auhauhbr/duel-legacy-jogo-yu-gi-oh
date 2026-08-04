<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Zones;

use DuelLegacy\DuelEngine\Cards\CardLocation;
use InvalidArgumentException;

final readonly class PlayerCardZonesDrawer
{
    public function draw(PlayerCardZones $zones): PlayerCardZones
    {
        $mainDeck = $zones->get(CardLocation::MAIN_DECK);
        if ($mainDeck->isEmpty()) {
            throw new InvalidArgumentException(
                'Não é possível comprar uma carta: o Deck Principal está vazio.',
            );
        }

        $topCard = $mainDeck->cards()[0];
        $destinationIndex = $zones->get(CardLocation::HAND)->count();

        return (new PlayerCardZonesMover)->move(
            $zones,
            $topCard->id,
            CardLocation::MAIN_DECK,
            CardLocation::HAND,
            $destinationIndex,
        );
    }
}
