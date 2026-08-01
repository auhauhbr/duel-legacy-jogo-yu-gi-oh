import type { PlayerId } from "../identifiers/identifiers.js";
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

function validateArguments(
  duelState: DuelState,
  profile: RulesProfile,
): asserts duelState is DuelState & {
  readonly currentPlayerId: PlayerId;
  readonly rngState: DeterministicRngState;
} {
  if (duelState.status !== "ACTIVE") {
    throw new Error("Somente um Duelo ACTIVE pode consultar o descarte.");
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

  if (
    !validateRulesProfile(profile).valid ||
    !Number.isInteger(profile.handLimit)
  ) {
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

export function getRequiredEndPhaseDiscardCount(
  duelState: DuelState,
  profile: RulesProfile,
): number {
  validateArguments(duelState, profile);

  const currentPlayer = duelState.players.find(
    ({ playerId }) => playerId === duelState.currentPlayerId,
  )!;

  return Math.max(0, currentPlayer.hand.length - profile.handLimit);
}
