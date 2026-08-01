import type { DuelId, PlayerId } from "../identifiers/identifiers.js";
import type { DuelPlayerState } from "../players/duel-player-state.js";
import type { RulesProfile } from "../rules/rules-profile.js";
import { validateRulesProfile } from "../rules/validate-rules-profile.js";
import type { DuelState } from "./duel-state.js";
import {
  validatePlayerZones,
  validateUniqueCardInstanceIds,
} from "./duel-state-validation.js";

function clonePlayerState(player: DuelPlayerState): DuelPlayerState {
  return {
    ...player,
    mainDeck: [...player.mainDeck],
    hand: [...player.hand],
    graveyard: [...player.graveyard],
    banishedFaceUp: [...player.banishedFaceUp],
    banishedFaceDown: [...player.banishedFaceDown],
    extraDeckFaceDown: [...player.extraDeckFaceDown],
    extraDeckFaceUp: [...player.extraDeckFaceUp],
    monsterZones: [...player.monsterZones],
    spellTrapZones: [...player.spellTrapZones],
  };
}

function validateArguments(
  duelId: DuelId,
  profile: RulesProfile,
  engineVersion: string,
  cardPoolVersion: string,
  players: readonly DuelPlayerState[],
  firstPlayerId: PlayerId,
): asserts players is readonly [DuelPlayerState, DuelPlayerState] {
  if (duelId.trim().length === 0) {
    throw new Error("duelId não pode ser vazio.");
  }

  if (!validateRulesProfile(profile).valid) {
    throw new Error("RulesProfile inválido.");
  }

  if (engineVersion.trim().length === 0) {
    throw new Error("engineVersion não pode ser vazia.");
  }

  if (cardPoolVersion.trim().length === 0) {
    throw new Error("cardPoolVersion não pode ser vazia.");
  }

  if (players.length !== 2) {
    throw new Error("O Duelo deve possuir exatamente dois jogadores.");
  }

  const [firstPlayer, secondPlayer] = players;

  if (!firstPlayer || !secondPlayer) {
    throw new Error("O Duelo deve possuir exatamente dois jogadores.");
  }

  if (firstPlayer.playerId === secondPlayer.playerId) {
    throw new Error("Os jogadores devem possuir IDs diferentes.");
  }

  if (
    firstPlayerId !== firstPlayer.playerId &&
    firstPlayerId !== secondPlayer.playerId
  ) {
    throw new Error("O jogador inicial deve pertencer ao Duelo.");
  }

  validatePlayerZones(players, profile);
  validateUniqueCardInstanceIds(players);
}

export function createInitialDuelState(
  duelId: DuelId,
  profile: RulesProfile,
  engineVersion: string,
  cardPoolVersion: string,
  players: readonly DuelPlayerState[],
  firstPlayerId: PlayerId,
): DuelState {
  validateArguments(
    duelId,
    profile,
    engineVersion,
    cardPoolVersion,
    players,
    firstPlayerId,
  );

  const [firstPlayer, secondPlayer] = players;
  const turnOrder: readonly [PlayerId, PlayerId] =
    firstPlayerId === firstPlayer.playerId
      ? [firstPlayer.playerId, secondPlayer.playerId]
      : [secondPlayer.playerId, firstPlayer.playerId];

  return {
    duelId,
    rulesProfileId: profile.id,
    engineVersion,
    cardPoolVersion,
    players: [clonePlayerState(firstPlayer), clonePlayerState(secondPlayer)],
    turnOrder,
    rngState: null,
    status: "PREPARING",
    turnNumber: 0,
    currentPlayerId: null,
    winnerId: null,
    resultReason: null,
  };
}
