import type { CardInstanceId, PlayerId } from "../identifiers/identifiers.js";
import { drawCardsFromMainDeck } from "../players/draw-cards-from-main-deck.js";
import type { DuelPlayerState } from "../players/duel-player-state.js";
import { createDeterministicRng } from "../random/deterministic-rng.js";
import { shuffleDeterministically } from "../random/shuffle-deterministically.js";
import type { RulesProfile } from "../rules/rules-profile.js";
import { validateRulesProfile } from "../rules/validate-rules-profile.js";
import type { DuelState } from "./duel-state.js";
import {
  validatePlayerZones,
  validateUniqueCardInstanceIds,
} from "./duel-state-validation.js";

function validateTurnOrder(duelState: DuelState): void {
  if (duelState.turnOrder.length !== 2) {
    throw new Error("turnOrder deve conter exatamente dois jogadores.");
  }

  const playerIds = new Set(duelState.players.map(({ playerId }) => playerId));
  const turnOrderIds = new Set(duelState.turnOrder);

  if (
    playerIds.size !== 2 ||
    turnOrderIds.size !== 2 ||
    duelState.turnOrder.some((playerId) => !playerIds.has(playerId))
  ) {
    throw new Error("turnOrder é incompatível com os jogadores do Duelo.");
  }
}

function validateArguments(
  duelState: DuelState,
  profile: RulesProfile,
  seed: string,
): void {
  if (duelState.status !== "PREPARING") {
    throw new Error("Somente um Duelo em PREPARING pode ser preparado.");
  }

  if (duelState.rngState !== null) {
    throw new Error("O Duelo já possui um estado de RNG.");
  }

  if (!validateRulesProfile(profile).valid) {
    throw new Error("RulesProfile inválido.");
  }

  if (profile.id !== duelState.rulesProfileId) {
    throw new Error("RulesProfile incompatível com o Duelo.");
  }

  if (seed.trim().length === 0) {
    throw new Error("A seed não pode ser vazia.");
  }

  if (duelState.players.length !== 2) {
    throw new Error("O Duelo deve possuir exatamente dois jogadores.");
  }

  validateTurnOrder(duelState);

  for (const player of duelState.players) {
    if (player.mainDeck.length < profile.startingHandSize) {
      throw new Error("Jogador sem cartas suficientes para a mão inicial.");
    }
  }

  validatePlayerZones(duelState.players, profile);
  validateUniqueCardInstanceIds(duelState.players);
}

function findPlayer(duelState: DuelState, playerId: PlayerId): DuelPlayerState {
  const player = duelState.players.find(
    (candidate) => candidate.playerId === playerId,
  );

  if (!player) {
    throw new Error("turnOrder é incompatível com os jogadores do Duelo.");
  }

  return player;
}

function withMainDeck(
  playerState: DuelPlayerState,
  mainDeck: readonly CardInstanceId[],
): DuelPlayerState {
  return {
    ...playerState,
    mainDeck: [...mainDeck],
  };
}

export function prepareInitialDuelState(
  duelState: DuelState,
  profile: RulesProfile,
  seed: string,
): DuelState {
  validateArguments(duelState, profile, seed);

  let rngState = createDeterministicRng(seed);
  const firstPlayer = findPlayer(duelState, duelState.turnOrder[0]);
  const firstShuffle = shuffleDeterministically(firstPlayer.mainDeck, rngState);
  rngState = firstShuffle.nextState;

  const secondPlayer = findPlayer(duelState, duelState.turnOrder[1]);
  const secondShuffle = shuffleDeterministically(
    secondPlayer.mainDeck,
    rngState,
  );
  rngState = secondShuffle.nextState;

  const preparedFirstPlayer = drawCardsFromMainDeck(
    withMainDeck(firstPlayer, firstShuffle.items),
    profile.startingHandSize,
  ).playerState;
  const preparedSecondPlayer = drawCardsFromMainDeck(
    withMainDeck(secondPlayer, secondShuffle.items),
    profile.startingHandSize,
  ).playerState;

  const preparedPlayers: [DuelPlayerState, DuelPlayerState] =
    duelState.players[0].playerId === preparedFirstPlayer.playerId
      ? [preparedFirstPlayer, preparedSecondPlayer]
      : [preparedSecondPlayer, preparedFirstPlayer];
  const turnOrder: [PlayerId, PlayerId] = [...duelState.turnOrder];

  Object.freeze(preparedPlayers);
  Object.freeze(turnOrder);

  return Object.freeze({
    ...duelState,
    players: preparedPlayers,
    turnOrder,
    rngState,
  });
}
