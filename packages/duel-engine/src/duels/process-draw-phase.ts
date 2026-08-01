import type { PlayerId } from "../identifiers/identifiers.js";
import { drawCardsFromMainDeck } from "../players/draw-cards-from-main-deck.js";
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
  readonly currentPlayerId: PlayerId;
  readonly rngState: DeterministicRngState;
} {
  if (duelState.status !== "ACTIVE") {
    throw new Error("Somente um Duelo ACTIVE pode processar a Fase de Compra.");
  }

  if (duelState.phase !== "DRAW") {
    throw new Error("O Duelo deve estar na fase DRAW.");
  }

  if (!Number.isInteger(duelState.turnNumber) || duelState.turnNumber < 1) {
    throw new Error("turnNumber deve ser um inteiro maior ou igual a 1.");
  }

  if (duelState.currentPlayerId === null) {
    throw new Error("A Fase de Compra deve possuir jogador atual.");
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

function clonePlayers(
  players: readonly [DuelPlayerState, DuelPlayerState],
): [DuelPlayerState, DuelPlayerState] {
  return [cloneAndFreezePlayer(players[0]), cloneAndFreezePlayer(players[1])];
}

function freezeState(
  duelState: DuelState,
  players: [DuelPlayerState, DuelPlayerState],
  rngState: DeterministicRngState,
  changes: Partial<DuelState>,
): DuelState {
  const turnOrder: [PlayerId, PlayerId] = [...duelState.turnOrder];

  Object.freeze(players);
  Object.freeze(turnOrder);

  return Object.freeze({
    ...duelState,
    ...changes,
    players,
    turnOrder,
    rngState: Object.freeze({ ...rngState }),
  });
}

export function processDrawPhase(
  duelState: DuelState,
  profile: RulesProfile,
): DuelState {
  validateArguments(duelState, profile);

  const players = clonePlayers(duelState.players);
  const currentPlayerIndex = players.findIndex(
    ({ playerId }) => playerId === duelState.currentPlayerId,
  );
  const currentPlayer = players[currentPlayerIndex];
  const shouldDraw = duelState.turnNumber > 1 || profile.drawOnFirstTurn;

  if (!shouldDraw) {
    return freezeState(duelState, players, duelState.rngState, {
      phase: "STANDBY",
    });
  }

  if (!currentPlayer || currentPlayer.mainDeck.length === 0) {
    const winner = players.find(
      ({ playerId }) => playerId !== duelState.currentPlayerId,
    );

    if (!winner) {
      throw new Error(
        "currentPlayerId é incompatível com turnOrder e com os jogadores.",
      );
    }

    return freezeState(duelState, players, duelState.rngState, {
      status: "FINISHED",
      winnerId: winner.playerId,
      resultReason: "DECK_OUT",
    });
  }

  players[currentPlayerIndex] = drawCardsFromMainDeck(
    currentPlayer,
    1,
  ).playerState;

  return freezeState(duelState, players, duelState.rngState, {
    phase: "STANDBY",
  });
}
