import type { PlayerId } from "../identifiers/identifiers.js";
import type { DuelPlayerState } from "../players/duel-player-state.js";
import type { DeterministicRngState } from "../random/deterministic-rng.js";
import type { RulesProfile } from "../rules/rules-profile.js";
import { validateRulesProfile } from "../rules/validate-rules-profile.js";
import type { DuelState } from "./duel-state.js";
import {
  validateExactlyTwoPlayers,
  validatePlayerZones,
  validateTurnOrder,
  validateUniqueCardInstanceIds,
} from "./duel-state-validation.js";

function freezeArray<T>(items: T[]): T[] {
  Object.freeze(items);
  return items;
}

function cloneAndFreezePlayer(player: DuelPlayerState): DuelPlayerState {
  return Object.freeze({
    ...player,
    mainDeck: freezeArray([...player.mainDeck]),
    hand: freezeArray([...player.hand]),
    graveyard: freezeArray([...player.graveyard]),
    banishedFaceUp: freezeArray([...player.banishedFaceUp]),
    banishedFaceDown: freezeArray([...player.banishedFaceDown]),
    extraDeckFaceDown: freezeArray([...player.extraDeckFaceDown]),
    extraDeckFaceUp: freezeArray([...player.extraDeckFaceUp]),
    monsterZones: freezeArray([...player.monsterZones]),
    spellTrapZones: freezeArray([...player.spellTrapZones]),
  });
}

function validateArguments(
  duelState: DuelState,
  profile: RulesProfile,
): asserts duelState is DuelState & {
  readonly rngState: DeterministicRngState;
} {
  if (duelState.status !== "PREPARING") {
    throw new Error("Somente um Duelo em PREPARING pode ser iniciado.");
  }

  if (duelState.rngState === null) {
    throw new Error("O Duelo deve possuir um estado de RNG.");
  }

  if (duelState.turnNumber !== 0) {
    throw new Error("O Duelo preparado deve estar antes do primeiro turno.");
  }

  if (duelState.currentPlayerId !== null) {
    throw new Error("O Duelo preparado não pode possuir jogador atual.");
  }

  if (duelState.phase !== null) {
    throw new Error("O Duelo preparado não pode possuir fase atual.");
  }

  if (duelState.winnerId !== null) {
    throw new Error("O Duelo preparado não pode possuir vencedor.");
  }

  if (duelState.resultReason !== null) {
    throw new Error("O Duelo preparado não pode possuir motivo de resultado.");
  }

  if (!validateRulesProfile(profile).valid) {
    throw new Error("RulesProfile inválido.");
  }

  if (profile.id !== duelState.rulesProfileId) {
    throw new Error("RulesProfile incompatível com o Duelo.");
  }

  validateExactlyTwoPlayers(duelState.players);
  validateTurnOrder(duelState.players, duelState.turnOrder);
  validatePlayerZones(duelState.players, profile);

  for (const player of duelState.players) {
    if (player.hand.length !== profile.startingHandSize) {
      throw new Error("A mão inicial é incompatível com o perfil.");
    }
  }

  validateUniqueCardInstanceIds(duelState.players);
}

function cloneAndFreezeRngState(
  rngState: DeterministicRngState,
): DeterministicRngState {
  return Object.freeze({ ...rngState });
}

export function startFirstTurn(
  duelState: DuelState,
  profile: RulesProfile,
): DuelState {
  validateArguments(duelState, profile);

  const players: [DuelPlayerState, DuelPlayerState] = [
    cloneAndFreezePlayer(duelState.players[0]),
    cloneAndFreezePlayer(duelState.players[1]),
  ];
  const turnOrder: [PlayerId, PlayerId] = [...duelState.turnOrder];

  Object.freeze(players);
  Object.freeze(turnOrder);

  return Object.freeze({
    ...duelState,
    players,
    turnOrder,
    rngState: cloneAndFreezeRngState(duelState.rngState),
    status: "ACTIVE",
    turnNumber: 1,
    currentPlayerId: turnOrder[0],
    phase: "DRAW",
  });
}
