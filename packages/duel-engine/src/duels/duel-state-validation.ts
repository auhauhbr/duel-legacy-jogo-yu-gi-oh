import type { CardInstanceId } from "../identifiers/identifiers.js";
import type { DuelPlayerState } from "../players/duel-player-state.js";
import type { RulesProfile } from "../rules/rules-profile.js";

function getCardInstanceIds(player: DuelPlayerState): CardInstanceId[] {
  const occupiedMonsterZones = player.monsterZones.filter(
    (cardId): cardId is CardInstanceId => cardId !== null,
  );
  const occupiedSpellTrapZones = player.spellTrapZones.filter(
    (cardId): cardId is CardInstanceId => cardId !== null,
  );

  return [
    ...player.mainDeck,
    ...player.hand,
    ...player.graveyard,
    ...player.banishedFaceUp,
    ...player.banishedFaceDown,
    ...player.extraDeckFaceDown,
    ...player.extraDeckFaceUp,
    ...occupiedMonsterZones,
    ...occupiedSpellTrapZones,
    ...(player.fieldZone === null ? [] : [player.fieldZone]),
  ];
}

export function validatePlayerZones(
  players: readonly DuelPlayerState[],
  profile: RulesProfile,
): void {
  for (const player of players) {
    if (
      player.monsterZones.length !== profile.mainMonsterZones ||
      player.spellTrapZones.length !== profile.spellTrapZones
    ) {
      throw new Error("As zonas do jogador são incompatíveis com o perfil.");
    }
  }
}

export function validateUniqueCardInstanceIds(
  players: readonly DuelPlayerState[],
): void {
  const allCardIds = players.flatMap(getCardInstanceIds);

  if (new Set(allCardIds).size !== allCardIds.length) {
    throw new Error("IDs de instância de carta devem ser únicos no Duelo.");
  }
}
