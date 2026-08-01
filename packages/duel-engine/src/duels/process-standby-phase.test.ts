import { describe, expect, it } from "vitest";

import {
  createDeterministicRng,
  createInitialPlayerState,
  gxLegacyProfile,
  processStandbyPhase,
  type DuelPlayerState,
  type DuelState,
  type PlayerId,
  type RulesProfile,
} from "../index.js";

const ARRAY_FIELDS = [
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

type ArrayField = (typeof ARRAY_FIELDS)[number];
type CardArea = ArrayField | "fieldZone";

function createPlayer(playerId: PlayerId): DuelPlayerState {
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

function createStandbyDuel(turnNumber = 1): DuelState {
  const turnOrder: [PlayerId, PlayerId] = ["player-1", "player-2"];

  return {
    duelId: "duel-standby",
    rulesProfileId: gxLegacyProfile.id,
    engineVersion: "engine-standby-1",
    cardPoolVersion: "pool-standby-1",
    players: [createPlayer("player-1"), createPlayer("player-2")],
    turnOrder,
    rngState: createDeterministicRng("standby-phase-seed"),
    status: "ACTIVE",
    turnNumber,
    currentPlayerId: turnOrder[(turnNumber - 1) % 2]!,
    phase: "STANDBY",
    winnerId: null,
    resultReason: null,
  };
}

function getArrays(player: DuelPlayerState): ReadonlyArray<readonly unknown[]> {
  return ARRAY_FIELDS.map((field) => player[field]);
}

function withDuplicateInArea(
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

describe("processStandbyPhase", () => {
  it("processa a Fase de Apoio e avança somente para MAIN_1", () => {
    const duel = createStandbyDuel();
    const result = processStandbyPhase(duel, gxLegacyProfile);

    expect(result).toEqual({ ...duel, phase: "MAIN_1" });
    expect(result).not.toBe(duel);
  });

  it("mantém status ACTIVE", () => {
    expect(
      processStandbyPhase(createStandbyDuel(), gxLegacyProfile).status,
    ).toBe("ACTIVE");
  });

  it("preserva turnNumber e currentPlayerId", () => {
    const duel = createStandbyDuel(7);
    const result = processStandbyPhase(duel, gxLegacyProfile);

    expect(result.turnNumber).toBe(duel.turnNumber);
    expect(result.currentPlayerId).toBe(duel.currentPlayerId);
  });

  it("funciona quando currentPlayerId corresponde a players[1]", () => {
    const duel = createStandbyDuel(2);
    const result = processStandbyPhase(duel, gxLegacyProfile);

    expect(duel.currentPlayerId).toBe(duel.players[1].playerId);
    expect(result.currentPlayerId).toBe(duel.players[1].playerId);
    expect(result.phase).toBe("MAIN_1");
  });

  it("continua sem vencedor e sem motivo de resultado", () => {
    const result = processStandbyPhase(createStandbyDuel(), gxLegacyProfile);

    expect(result.winnerId).toBeNull();
    expect(result.resultReason).toBeNull();
  });

  it("preserva mãos, Decks Principais e todas as áreas dos jogadores", () => {
    const duel = createStandbyDuel();
    const result = processStandbyPhase(duel, gxLegacyProfile);

    for (const playerIndex of [0, 1] as const) {
      expect(result.players[playerIndex]).toEqual(duel.players[playerIndex]);
      for (const field of ARRAY_FIELDS) {
        expect(result.players[playerIndex][field]).toEqual(
          duel.players[playerIndex][field],
        );
      }
      expect(result.players[playerIndex].fieldZone).toBe(
        duel.players[playerIndex].fieldZone,
      );
    }
  });

  it("preserva Pontos de Vida e contadores de Invocação-Normal", () => {
    const duel = createStandbyDuel();
    const result = processStandbyPhase(duel, gxLegacyProfile);

    for (const playerIndex of [0, 1] as const) {
      expect(result.players[playerIndex].lifePoints).toBe(
        duel.players[playerIndex].lifePoints,
      );
      expect(result.players[playerIndex].normalSummonsUsed).toBe(
        duel.players[playerIndex].normalSummonsUsed,
      );
      expect(result.players[playerIndex].normalSummonLimit).toBe(
        duel.players[playerIndex].normalSummonLimit,
      );
    }
  });

  it("preserva calls, seed e state do RNG sem consumo", () => {
    const duel = createStandbyDuel();
    const result = processStandbyPhase(duel, gxLegacyProfile);

    expect(result.rngState?.calls).toBe(duel.rngState?.calls);
    expect(result.rngState?.seed).toBe(duel.rngState?.seed);
    expect(result.rngState?.state).toBe(duel.rngState?.state);
    expect(result.rngState).toEqual(duel.rngState);
  });

  it("preserva turnOrder, identificadores e versões", () => {
    const duel = createStandbyDuel();
    const result = processStandbyPhase(duel, gxLegacyProfile);

    expect(result.turnOrder).toEqual(duel.turnOrder);
    expect(result.duelId).toBe(duel.duelId);
    expect(result.rulesProfileId).toBe(duel.rulesProfileId);
    expect(result.engineVersion).toBe(duel.engineVersion);
    expect(result.cardPoolVersion).toBe(duel.cardPoolVersion);
  });

  it("não modifica DuelState, RulesProfile ou jogadores recebidos", () => {
    const duel = createStandbyDuel();
    const profile: RulesProfile = {
      ...gxLegacyProfile,
      enabledSummons: [...gxLegacyProfile.enabledSummons],
    };
    const duelSnapshot = structuredClone(duel);
    const profileSnapshot = structuredClone(profile);
    const playerSnapshots = duel.players.map((player) =>
      structuredClone(player),
    );

    processStandbyPhase(duel, profile);

    expect(duel).toEqual(duelSnapshot);
    expect(profile).toEqual(profileSnapshot);
    expect(duel.players[0]).toEqual(playerSnapshots[0]);
    expect(duel.players[1]).toEqual(playerSnapshots[1]);
  });

  it("saída não compartilha objetos ou arrays mutáveis com a entrada", () => {
    const duel = createStandbyDuel();
    const result = processStandbyPhase(duel, gxLegacyProfile);

    expect(result.players).not.toBe(duel.players);
    expect(result.turnOrder).not.toBe(duel.turnOrder);
    expect(result.rngState).not.toBe(duel.rngState);

    for (const playerIndex of [0, 1] as const) {
      expect(result.players[playerIndex]).not.toBe(duel.players[playerIndex]);
      const inputArrays = getArrays(duel.players[playerIndex]);
      const outputArrays = getArrays(result.players[playerIndex]);

      for (const [index, inputArray] of inputArrays.entries()) {
        expect(outputArrays[index]).not.toBe(inputArray);
      }
    }
  });

  it("duas execuções independentes não compartilham arrays", () => {
    const duel = createStandbyDuel();
    const first = processStandbyPhase(duel, gxLegacyProfile);
    const second = processStandbyPhase(duel, gxLegacyProfile);

    expect(first.players).not.toBe(second.players);
    expect(first.turnOrder).not.toBe(second.turnOrder);

    for (const playerIndex of [0, 1] as const) {
      expect(first.players[playerIndex]).not.toBe(second.players[playerIndex]);
      const firstArrays = getArrays(first.players[playerIndex]);
      const secondArrays = getArrays(second.players[playerIndex]);

      for (const [index, firstArray] of firstArrays.entries()) {
        expect(secondArrays[index]).not.toBe(firstArray);
      }
    }
  });

  it("congela estado, jogadores, RNG e arrays retornados", () => {
    const result = processStandbyPhase(createStandbyDuel(), gxLegacyProfile);

    expect(Object.isFrozen(result)).toBe(true);
    expect(Object.isFrozen(result.players)).toBe(true);
    expect(Object.isFrozen(result.turnOrder)).toBe(true);
    expect(Object.isFrozen(result.rngState)).toBe(true);

    for (const player of result.players) {
      expect(Object.isFrozen(player)).toBe(true);
      for (const array of getArrays(player)) {
        expect(Object.isFrozen(array)).toBe(true);
      }
    }
  });

  it.each(["PREPARING", "FINISHED"] as const)("rejeita status %s", (status) => {
    const invalid: DuelState = { ...createStandbyDuel(), status };

    expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
      "Somente um Duelo ACTIVE pode processar a Fase de Apoio.",
    );
  });

  it.each(["DRAW", "MAIN_1", "BATTLE", "MAIN_2", "END"] as const)(
    "rejeita phase %s",
    (phase) => {
      const invalid: DuelState = { ...createStandbyDuel(), phase };

      expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
        "O Duelo deve estar na fase STANDBY.",
      );
    },
  );

  it.each([0, -1, -10, 1.5])("rejeita turnNumber inválido %s", (turnNumber) => {
    const invalid: DuelState = { ...createStandbyDuel(), turnNumber };

    expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
      "turnNumber deve ser um inteiro maior ou igual a 1.",
    );
  });

  it("rejeita currentPlayerId null", () => {
    const invalid: DuelState = {
      ...createStandbyDuel(),
      currentPlayerId: null,
    };

    expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
      "A Fase de Apoio deve possuir jogador atual.",
    );
  });

  it("rejeita jogador atual desconhecido", () => {
    const invalid: DuelState = {
      ...createStandbyDuel(),
      currentPlayerId: "unknown-player",
    };

    expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
      "currentPlayerId é incompatível com turnOrder e com os jogadores.",
    );
  });

  it("rejeita currentPlayerId incompatível com o turno", () => {
    const invalid: DuelState = {
      ...createStandbyDuel(2),
      currentPlayerId: "player-1",
    };

    expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
      "currentPlayerId é incompatível com turnOrder e com os jogadores.",
    );
  });

  it("rejeita winnerId já definido", () => {
    const invalid: DuelState = {
      ...createStandbyDuel(),
      winnerId: "player-2",
    };

    expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
      "Um Duelo ACTIVE não pode possuir vencedor.",
    );
  });

  it("rejeita resultReason já definido", () => {
    const invalid: DuelState = {
      ...createStandbyDuel(),
      resultReason: "DECK_OUT",
    };

    expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
      "Um Duelo ACTIVE não pode possuir motivo de resultado.",
    );
  });

  it("rejeita rngState null", () => {
    const invalid: DuelState = { ...createStandbyDuel(), rngState: null };

    expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
      "O Duelo deve possuir um estado de RNG.",
    );
  });

  it("rejeita RulesProfile inválido", () => {
    const invalidProfile: RulesProfile = {
      ...gxLegacyProfile,
      startingLifePoints: 0,
    };

    expect(() =>
      processStandbyPhase(createStandbyDuel(), invalidProfile),
    ).toThrow("RulesProfile inválido.");
  });

  it("rejeita RulesProfile incompatível", () => {
    const incompatibleProfile: RulesProfile = {
      ...gxLegacyProfile,
      id: "OTHER_PROFILE",
    };

    expect(() =>
      processStandbyPhase(createStandbyDuel(), incompatibleProfile),
    ).toThrow("RulesProfile incompatível com o Duelo.");
  });

  it.each([1, 3])("rejeita quantidade de jogadores igual a %i", (amount) => {
    const duel = createStandbyDuel();
    const players = [...duel.players, createPlayer("player-3")].slice(
      0,
      amount,
    );
    const invalid = { ...duel, players } as unknown as DuelState;

    expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
      "O Duelo deve possuir exatamente dois jogadores.",
    );
  });

  it("rejeita jogadores com IDs iguais", () => {
    const duel = createStandbyDuel();
    const duplicateIdPlayer: DuelPlayerState = {
      ...duel.players[1],
      playerId: duel.players[0].playerId,
    };
    const invalid: DuelState = {
      ...duel,
      players: [duel.players[0], duplicateIdPlayer],
    };

    expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
      "Os jogadores devem possuir IDs diferentes.",
    );
  });

  it.each([
    ["apenas um ID", ["player-1"]],
    ["três IDs", ["player-1", "player-2", "player-1"]],
    ["IDs repetidos", ["player-1", "player-1"]],
    ["jogador inexistente", ["player-1", "unknown-player"]],
  ])("rejeita turnOrder inválido: %s", (_scenario, turnOrder) => {
    const invalid = {
      ...createStandbyDuel(),
      turnOrder,
    } as unknown as DuelState;

    expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
      /turnOrder/,
    );
  });

  it.each(["monsterZones", "spellTrapZones"] as const)(
    "rejeita quantidade incompatível em %s",
    (zoneField) => {
      const duel = createStandbyDuel();
      const invalidPlayer: DuelPlayerState = {
        ...duel.players[0],
        [zoneField]: [],
      };
      const invalid: DuelState = {
        ...duel,
        players: [invalidPlayer, duel.players[1]],
      };

      expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
        "As zonas do jogador são incompatíveis com o perfil.",
      );
    },
  );

  it.each([
    "mainDeck",
    "hand",
    "graveyard",
    "banishedFaceUp",
    "banishedFaceDown",
    "extraDeckFaceDown",
    "extraDeckFaceUp",
    "monsterZones",
    "spellTrapZones",
    "fieldZone",
  ] satisfies CardArea[])("rejeita ID global duplicado em %s", (area) => {
    const duel = createStandbyDuel();
    const duplicateId = duel.players[0].hand[0]!;
    const invalidSecondPlayer = withDuplicateInArea(
      duel.players[1],
      area,
      duplicateId,
    );
    const invalid: DuelState = {
      ...duel,
      players: [duel.players[0], invalidSecondPlayer],
    };

    expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow(
      "IDs de instância de carta devem ser únicos no Duelo.",
    );
  });

  it("erro não modifica a entrada", () => {
    const invalid: DuelState = { ...createStandbyDuel(), turnNumber: 0 };
    const snapshot = structuredClone(invalid);

    expect(() => processStandbyPhase(invalid, gxLegacyProfile)).toThrow();
    expect(invalid).toEqual(snapshot);
  });
});
