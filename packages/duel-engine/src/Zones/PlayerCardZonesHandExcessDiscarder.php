<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Zones;

use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Identifiers\CardInstanceId;
use InvalidArgumentException;

final readonly class PlayerCardZonesHandExcessDiscarder
{
    /**
     * @param  list<CardInstanceId>  $selectedCardIds
     */
    public function discardExcess(
        PlayerCardZones $zones,
        int $maximumHandSize,
        array $selectedCardIds,
    ): PlayerCardZones {
        if ($maximumHandSize < 0) {
            throw new InvalidArgumentException(
                "O limite máximo da mão não pode ser negativo: {$maximumHandSize}.",
            );
        }

        $selectedIdsByKey = self::validateSelection($selectedCardIds);
        $expectedDiscardCount = max(0, $zones->get(CardLocation::HAND)->count() - $maximumHandSize);
        $actualDiscardCount = count($selectedCardIds);

        if ($actualDiscardCount !== $expectedDiscardCount) {
            throw new InvalidArgumentException(
                "A quantidade de cartas selecionadas para descarte deve ser {$expectedDiscardCount}; recebida: {$actualDiscardCount}.",
            );
        }

        $hand = $zones->get(CardLocation::HAND);
        foreach ($selectedCardIds as $selectedCardId) {
            if (! $hand->contains($selectedCardId)) {
                throw new InvalidArgumentException(
                    "CardInstanceId {$selectedCardId->value} não foi encontrado na localização de origem HAND.",
                );
            }
        }

        if ($expectedDiscardCount === 0) {
            return $zones;
        }

        $orderedCardIds = [];
        foreach ($hand->cards() as $card) {
            $key = self::idKey($card->id);
            if (isset($selectedIdsByKey[$key])) {
                $orderedCardIds[] = $card->id;
            }
        }

        $result = $zones;
        $discarder = new PlayerCardZonesDiscarder;
        foreach ($orderedCardIds as $cardId) {
            $result = $discarder->discard($result, $cardId);
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $selectedCardIds
     * @return array<string, CardInstanceId>
     */
    private static function validateSelection(array $selectedCardIds): array
    {
        if (! array_is_list($selectedCardIds)) {
            throw new InvalidArgumentException(
                'Os IDs selecionados para descarte devem formar uma lista de CardInstanceId.',
            );
        }

        $selectedIdsByKey = [];
        foreach ($selectedCardIds as $selectedCardId) {
            if (! $selectedCardId instanceof CardInstanceId) {
                throw new InvalidArgumentException(
                    'Os IDs selecionados para descarte devem formar uma lista de CardInstanceId.',
                );
            }

            $key = self::idKey($selectedCardId);
            if (isset($selectedIdsByKey[$key])) {
                throw new InvalidArgumentException(
                    "CardInstanceId duplicado na seleção de descarte: {$selectedCardId->value}.",
                );
            }

            $selectedIdsByKey[$key] = $selectedCardId;
        }

        return $selectedIdsByKey;
    }

    private static function idKey(CardInstanceId $cardId): string
    {
        return "id:{$cardId->value}";
    }
}
