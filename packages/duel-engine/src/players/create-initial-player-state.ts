import type { CardInstanceId, PlayerId } from "../identifiers/identifiers.js";
import type { RulesProfile } from "../rules/rules-profile.js";
import { validateRulesProfile } from "../rules/validate-rules-profile.js";
import type { DuelPlayerState } from "./duel-player-state.js";

function validateArguments(
  profile: RulesProfile,
  playerId: PlayerId,
  mainDeck: readonly CardInstanceId[],
  extraDeck: readonly CardInstanceId[],
): void {
  if (!validateRulesProfile(profile).valid) {
    throw new Error("RulesProfile inválido.");
  }

  if (playerId.trim().length === 0) {
    throw new Error("playerId não pode ser vazio.");
  }

  const allCardIds = [...mainDeck, ...extraDeck];

  if (allCardIds.some((cardId) => cardId.trim().length === 0)) {
    throw new Error("IDs de instância de carta não podem ser vazios.");
  }

  if (new Set(allCardIds).size !== allCardIds.length) {
    throw new Error("IDs de instância de carta devem ser únicos.");
  }

  if (extraDeck.length > profile.extraDeckMax) {
    throw new Error("Deck Adicional excede o limite do perfil.");
  }
}

export function createInitialPlayerState(
  profile: RulesProfile,
  playerId: PlayerId,
  mainDeck: readonly CardInstanceId[],
  extraDeck: readonly CardInstanceId[],
): DuelPlayerState {
  validateArguments(profile, playerId, mainDeck, extraDeck);

  return {
    playerId,
    lifePoints: profile.startingLifePoints,

    mainDeck: [...mainDeck],
    hand: [],
    graveyard: [],
    banishedFaceUp: [],
    banishedFaceDown: [],
    extraDeckFaceDown: [...extraDeck],
    extraDeckFaceUp: [],

    monsterZones: Array.from(
      { length: profile.mainMonsterZones },
      (): CardInstanceId | null => null,
    ),
    spellTrapZones: Array.from(
      { length: profile.spellTrapZones },
      (): CardInstanceId | null => null,
    ),
    fieldZone: null,

    normalSummonsUsed: 0,
    normalSummonLimit: 1,
  };
}
