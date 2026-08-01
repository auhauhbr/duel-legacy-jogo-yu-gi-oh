import type { PlayerId } from "../identifiers/identifiers.js";
import type { DuelPlayerState } from "../players/duel-player-state.js";
import type { DuelPhase } from "../phases/duel-phase.js";
import type { RulesProfile } from "../rules/rules-profile.js";
import type { DuelState } from "./duel-state.js";
import { getLegalMainPhaseOneTransitions } from "./get-legal-main-phase-one-transitions.js";

function cloneAndFreezeArray<T>(items: readonly T[]): T[] {
  const clone = [...items];
  Object.freeze(clone);
  return clone;
}

function cloneAndFreezePlayer(player: DuelPlayerState): DuelPlayerState {
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
  });
}

export function transitionFromMainPhaseOne(
  duelState: DuelState,
  profile: RulesProfile,
  targetPhase: DuelPhase,
): DuelState {
  const legalTransitions = getLegalMainPhaseOneTransitions(duelState, profile);

  if (!legalTransitions.includes(targetPhase)) {
    throw new Error(`Transição MAIN_1 → ${targetPhase} não permitida.`);
  }

  const rngState = duelState.rngState;

  if (rngState === null) {
    throw new Error("O Duelo deve possuir um estado de RNG.");
  }

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
    rngState: Object.freeze({ ...rngState }),
    phase: targetPhase,
  });
}
