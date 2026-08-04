<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Zones;

use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Identifiers\CardInstanceId;

final readonly class PlayerCardZonesDiscarder
{
    public function discard(
        PlayerCardZones $zones,
        CardInstanceId $cardId,
    ): PlayerCardZones {
        $destinationIndex = $zones->get(CardLocation::GRAVEYARD)->count();

        return (new PlayerCardZonesMover)->move(
            $zones,
            $cardId,
            CardLocation::HAND,
            CardLocation::GRAVEYARD,
            $destinationIndex,
        );
    }
}
