<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Players;

use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Zones\OrderedCardZone;
use DuelLegacy\DuelEngine\Zones\PlayerCardZones;
use InvalidArgumentException;

final readonly class PlayerCardZonesProjector
{
    /** @var array<string, CardInstance> */
    private array $instancesById;

    /** @param list<CardInstance> $availableInstances */
    public function __construct(array $availableInstances)
    {
        $this->instancesById = self::indexAvailableInstances($availableInstances);
    }

    public function project(DuelPlayerState $playerState): PlayerCardZones
    {
        return new PlayerCardZones(
            mainDeck: $this->projectZone($playerState->mainDeck, CardLocation::MAIN_DECK),
            hand: $this->projectZone($playerState->hand, CardLocation::HAND),
            graveyard: $this->projectZone($playerState->graveyard, CardLocation::GRAVEYARD),
            banishedFaceUp: $this->projectZone($playerState->banishedFaceUp, CardLocation::BANISHED_FACE_UP),
            banishedFaceDown: $this->projectZone($playerState->banishedFaceDown, CardLocation::BANISHED_FACE_DOWN),
            extraDeckFaceDown: $this->projectZone($playerState->extraDeckFaceDown, CardLocation::EXTRA_DECK_FACE_DOWN),
            extraDeckFaceUp: $this->projectZone($playerState->extraDeckFaceUp, CardLocation::EXTRA_DECK_FACE_UP),
        );
    }

    /**
     * @param  array<array-key, mixed>  $availableInstances
     * @return array<string, CardInstance>
     */
    private static function indexAvailableInstances(array $availableInstances): array
    {
        if (! array_is_list($availableInstances)) {
            throw new InvalidArgumentException('availableInstances deve ser uma lista.');
        }

        $instancesById = [];
        foreach ($availableInstances as $instance) {
            if (! $instance instanceof CardInstance) {
                throw new InvalidArgumentException('availableInstances deve conter apenas CardInstance.');
            }

            $key = "id:{$instance->id->value}";
            if (isset($instancesById[$key])) {
                throw new InvalidArgumentException(
                    "CardInstanceId duplicado na coleção disponível: {$instance->id->value}.",
                );
            }

            $instancesById[$key] = $instance;
        }

        return $instancesById;
    }

    /**
     * @param  list<string>  $instanceIds
     */
    private function projectZone(array $instanceIds, CardLocation $location): OrderedCardZone
    {
        $instances = [];
        foreach ($instanceIds as $instanceId) {
            $key = "id:{$instanceId}";
            if (! isset($this->instancesById[$key])) {
                throw new InvalidArgumentException(
                    "CardInstance não encontrada para o ID {$instanceId} na localização {$location->value}.",
                );
            }

            $instances[] = $this->instancesById[$key];
        }

        return new OrderedCardZone($location, $instances);
    }
}
