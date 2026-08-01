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

function cloneAndFreezeArray<T>(items: readonly T[]): T[] {
  const clone = [...items];
  Object.freeze(clone);
  return clone;
}

function cloneAndFreezePlayer(
  player: DuelPlayerState,
  startingPlayerId: PlayerId,
): DuelPlayerState {
  return Object.freeze({
    ...player,
    mainDeck: cloneAndFreezeArray(player.mainDeck),
    hand: cloneAndFreezeArray(player.hand),
    graveyard: cloneAndFreezeArray(player.graveyard),
    banishedFaceUp: cloneAndFreezeArray(player.banishedFaceUp),
    banishedFaceDown: cloneAndFreezeArray(player.banishedFaceDown),
    extraDeckFaceDown: cloneAndFreezeArray(player.extraDeckFaceDown),
    extraDeckFaceUp: cloneAndFreezeArray(player.extraDeckFaceUp),
    monsterZones: cloneAndFreezeArray(player.monsterZones),
    spellTrapZones: cloneAndFreezeArray(player.spellTrapZones),
    normalSummonsUsed:
      player.playerId === startingPlayerId ? 0 : player.normalSummonsUsed,
  });
}

function validateArguments(
  duelState: DuelState,
  profile: RulesProfile,
): asserts duelState is DuelState & {
  readonly currentPlayerId: PlayerId;
  readonly rngState: DeterministicRngState;
} {
  if (duelState.status !== "ACTIVE") {
    throw new Error("Somente um Duelo ACTIVE pode iniciar o próximo turno.");
  }

  if (duelState.phase !== "END") {
    throw new Error("O Duelo deve estar na fase END.");
  }

  if (!Number.isInteger(duelState.turnNumber) || duelState.turnNumber < 1) {
    throw new Error("turnNumber deve ser um inteiro maior ou igual a 1.");
  }

  if (duelState.currentPlayerId === null) {
    throw new Error("A Fase Final deve possuir jogador atual.");
  }

  if (duelState.winnerId !== null) {
    throw new Error("Um Duelo ACTIVE não pode possuir vencedor.");
  }

  if (duelState.resultReason !== null) {
    throw new Error("Um Duelo ACTIVE não pode possuir motivo de resultado.");
  }

  if (duelState.rngState === null) {
    throw new Error("O Duelo deve possuir um estado de RNG.");
  }

  if (!validateRulesProfile(profile).valid) {
    throw new Error("RulesProfile inválido.");
  }

  if (profile.id !== duelState.rulesProfileId) {
    throw new Error("RulesProfile incompatível com o Duelo.");
  }

  validateExactlyTwoPlayers(duelState.players);
  validateTurnOrder(duelState.players, duelState.turnOrder);

  const expectedCurrentPlayerId =
    duelState.turnOrder[(duelState.turnNumber - 1) % 2];

  if (
    expectedCurrentPlayerId === undefined ||
    duelState.currentPlayerId !== expectedCurrentPlayerId ||
    !duelState.players.some(
      ({ playerId }) => playerId === duelState.currentPlayerId,
    )
  ) {
    throw new Error(
      "currentPlayerId é incompatível com turnOrder e com os jogadores.",
    );
  }

  validatePlayerZones(duelState.players, profile);
  validateUniqueCardInstanceIds(duelState.players);
}

export function startNextTurn(
  duelState: DuelState,
  profile: RulesProfile,
): DuelState {
  validateArguments(duelState, profile);

  const nextTurnNumber = duelState.turnNumber + 1;
  const nextPlayerId = duelState.turnOrder[(nextTurnNumber - 1) % 2]!;
  const players: [DuelPlayerState, DuelPlayerState] = [
    cloneAndFreezePlayer(duelState.players[0], nextPlayerId),
    cloneAndFreezePlayer(duelState.players[1], nextPlayerId),
  ];
  const turnOrder: [PlayerId, PlayerId] = [...duelState.turnOrder];

  Object.freeze(players);
  Object.freeze(turnOrder);

  return Object.freeze({
    ...duelState,
    players,
    turnOrder,
    rngState: Object.freeze({ ...duelState.rngState }),
    turnNumber: nextTurnNumber,
    currentPlayerId: nextPlayerId,
    phase: "DRAW",
  });
}
