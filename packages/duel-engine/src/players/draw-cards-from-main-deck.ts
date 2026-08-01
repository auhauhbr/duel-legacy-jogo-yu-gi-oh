import type { CardInstanceId } from "../identifiers/identifiers.js";
import type { DuelPlayerState } from "./duel-player-state.js";

export interface DrawCardsResult {
  readonly playerState: DuelPlayerState;
  readonly drawnCardIds: readonly CardInstanceId[];
}

function freezeArray<T>(items: T[]): T[] {
  Object.freeze(items);
  return items;
}

export function drawCardsFromMainDeck(
  playerState: DuelPlayerState,
  amount: number,
): DrawCardsResult {
  if (!Number.isFinite(amount) || !Number.isInteger(amount)) {
    throw new Error("A quantidade de compra deve ser um inteiro finito.");
  }

  if (amount < 0) {
    throw new Error("A quantidade de compra não pode ser negativa.");
  }

  if (amount > playerState.mainDeck.length) {
    throw new Error("O Deck Principal não possui cartas suficientes.");
  }

  const drawnCardIds = freezeArray(playerState.mainDeck.slice(0, amount));
  const nextPlayerState: DuelPlayerState = Object.freeze({
    ...playerState,
    mainDeck: freezeArray(playerState.mainDeck.slice(amount)),
    hand: freezeArray([...playerState.hand, ...drawnCardIds]),
    graveyard: freezeArray([...playerState.graveyard]),
    banishedFaceUp: freezeArray([...playerState.banishedFaceUp]),
    banishedFaceDown: freezeArray([...playerState.banishedFaceDown]),
    extraDeckFaceDown: freezeArray([...playerState.extraDeckFaceDown]),
    extraDeckFaceUp: freezeArray([...playerState.extraDeckFaceUp]),
    monsterZones: freezeArray([...playerState.monsterZones]),
    spellTrapZones: freezeArray([...playerState.spellTrapZones]),
  });

  return Object.freeze({
    playerState: nextPlayerState,
    drawnCardIds,
  });
}
