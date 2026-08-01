import type { PlayerId } from "../identifiers/identifiers.js";
import type { DuelPhase } from "../phases/duel-phase.js";
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

export function validateMainPhaseOneState(
  duelState: DuelState,
  profile: RulesProfile,
): asserts duelState is DuelState & {
  readonly currentPlayerId: PlayerId;
  readonly rngState: DeterministicRngState;
} {
  if (duelState.status !== "ACTIVE") {
    throw new Error(
      "Somente um Duelo ACTIVE pode processar a Fase Principal 1.",
    );
  }

  if (duelState.phase !== "MAIN_1") {
    throw new Error("O Duelo deve estar na fase MAIN_1.");
  }

  if (!Number.isInteger(duelState.turnNumber) || duelState.turnNumber < 1) {
    throw new Error("turnNumber deve ser um inteiro maior ou igual a 1.");
  }

  if (duelState.currentPlayerId === null) {
    throw new Error("A Fase Principal 1 deve possuir jogador atual.");
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

export function getLegalMainPhaseOneTransitions(
  duelState: DuelState,
  profile: RulesProfile,
): readonly DuelPhase[] {
  validateMainPhaseOneState(duelState, profile);

  const transitions: DuelPhase[] = [];

  if (duelState.turnNumber > 1 || profile.battleOnFirstTurn) {
    transitions.push("BATTLE");
  }

  transitions.push("END");

  return Object.freeze(transitions);
}
