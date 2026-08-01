import {
  createDeterministicRng,
  createInitialPlayerState,
  gxLegacyProfile,
  type DuelPlayerState,
  type DuelState,
  type PlayerId,
  type RulesProfile,
} from "../index.js";

export const PLAYER_ARRAY_FIELDS = [
  "mainDeck",
  "hand",
  "graveyard",
  "banishedFaceUp",
  "banishedFaceDown",
  "extraDeckFaceDown",
  "extraDeckFaceUp",
  "monsterZones",
  "spellTrapZones",
] as const;

export type PlayerArrayField = (typeof PLAYER_ARRAY_FIELDS)[number];
export type CardArea = PlayerArrayField | "fieldZone";

export function createMainPhaseOnePlayer(playerId: PlayerId): DuelPlayerState {
  const player = createInitialPlayerState(
    gxLegacyProfile,
    playerId,
    [`${playerId}-deck-1`, `${playerId}-deck-2`],
    [`${playerId}-extra-down`],
  );

  return {
    ...player,
    lifePoints: playerId === "player-1" ? 7100 : 6200,
    hand: [`${playerId}-hand`],
    graveyard: [`${playerId}-graveyard`],
    banishedFaceUp: [`${playerId}-banished-up`],
    banishedFaceDown: [`${playerId}-banished-down`],
    extraDeckFaceUp: [`${playerId}-extra-up`],
    monsterZones: [null, `${playerId}-monster`, null, null, null],
    spellTrapZones: [null, null, `${playerId}-spell`, null, null],
    fieldZone: `${playerId}-field`,
    normalSummonsUsed: playerId === "player-1" ? 1 : 0,
    normalSummonLimit: playerId === "player-1" ? 2 : 1,
  };
}

export function createMainPhaseOneDuel(turnNumber = 1): DuelState {
  const turnOrder: [PlayerId, PlayerId] = ["player-1", "player-2"];

  return {
    duelId: "duel-main-phase-one",
    rulesProfileId: gxLegacyProfile.id,
    engineVersion: "engine-main-phase-one-1",
    cardPoolVersion: "pool-main-phase-one-1",
    players: [
      createMainPhaseOnePlayer("player-1"),
      createMainPhaseOnePlayer("player-2"),
    ],
    turnOrder,
    rngState: createDeterministicRng("main-phase-one-seed"),
    status: "ACTIVE",
    turnNumber,
    currentPlayerId: turnOrder[(turnNumber - 1) % 2]!,
    phase: "MAIN_1",
    winnerId: null,
    resultReason: null,
  };
}

export function createFirstTurnBattleProfile(): RulesProfile {
  return {
    ...gxLegacyProfile,
    id: "FIRST_TURN_BATTLE",
    battleOnFirstTurn: true,
    enabledSummons: [...gxLegacyProfile.enabledSummons],
  };
}

export function withDuplicateInArea(
  player: DuelPlayerState,
  area: CardArea,
  duplicateId: string,
): DuelPlayerState {
  if (area === "fieldZone") {
    return { ...player, fieldZone: duplicateId };
  }

  return {
    ...player,
    [area]: [duplicateId, ...player[area].slice(1)],
  };
}
