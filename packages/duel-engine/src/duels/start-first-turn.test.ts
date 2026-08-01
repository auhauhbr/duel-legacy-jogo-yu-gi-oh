import { describe, expect, it } from "vitest";

import {
  createInitialDuelState,
  createInitialPlayerState,
  gxLegacyProfile,
  prepareInitialDuelState,
  startFirstTurn,
  type DuelPhase,
  type DuelPlayerState,
  type DuelState,
  type DuelStatus,
  type PlayerId,
  type RulesProfile,
} from "../index.js";

const CARD_ARRAY_FIELDS = [
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

type CardArrayField = (typeof CARD_ARRAY_FIELDS)[number];
type CardArea = CardArrayField | "fieldZone";

function createPlayer(playerId: PlayerId): DuelPlayerState {
  const player = createInitialPlayerState(
    gxLegacyProfile,
    playerId,
    Array.from({ length: 10 }, (_, index) => `${playerId}-main-${index}`),
    [`${playerId}-extra-down`],
  );

  return {
    ...player,
    graveyard: [`${playerId}-graveyard`],
    banishedFaceUp: [`${playerId}-banished-up`],
    banishedFaceDown: [`${playerId}-banished-down`],
    extraDeckFaceUp: [`${playerId}-extra-up`],
    monsterZones: [`${playerId}-monster`, null, null, null, null],
    spellTrapZones: [null, `${playerId}-spell`, null, null, null],
    fieldZone: `${playerId}-field`,
  };
}

function createPreparedDuel(firstPlayerId: PlayerId = "player-1"): DuelState {
  const initial = createInitialDuelState(
    "duel-1",
    gxLegacyProfile,
    "engine-1",
    "pool-1",
    [createPlayer("player-1"), createPlayer("player-2")],
    firstPlayerId,
  );

  return prepareInitialDuelState(initial, gxLegacyProfile, "start-turn-seed");
}

function getPlayerArrays(
  player: DuelPlayerState,
): ReadonlyArray<readonly unknown[]> {
  return CARD_ARRAY_FIELDS.map((field) => player[field]);
}

function withDuplicateInArea(
  player: DuelPlayerState,
  area: CardArea,
  duplicateId: string,
): DuelPlayerState {
  if (area === "fieldZone") {
    return { ...player, fieldZone: duplicateId };
  }

  const currentArea = player[area];
  return {
    ...player,
    [area]: [duplicateId, ...currentArea.slice(1)],
  };
}

describe("startFirstTurn", () => {
  it("inicia corretamente o primeiro turno", () => {
    const started = startFirstTurn(createPreparedDuel(), gxLegacyProfile);

    expect(started.status).toBe("ACTIVE");
    expect(started.turnNumber).toBe(1);
    expect(started.currentPlayerId).toBe(started.turnOrder[0]);
    expect(started.phase).toBe("DRAW");
  });

  it("usa turnOrder[0] mesmo quando ele é o segundo item de players", () => {
    const prepared = createPreparedDuel("player-2");
    const started = startFirstTurn(prepared, gxLegacyProfile);

    expect(started.players[1].playerId).toBe("player-2");
    expect(started.turnOrder[0]).toBe("player-2");
    expect(started.currentPlayerId).toBe("player-2");
  });

  it("continua sem vencedor e sem motivo de resultado", () => {
    const started = startFirstTurn(createPreparedDuel(), gxLegacyProfile);

    expect(started.winnerId).toBeNull();
    expect(started.resultReason).toBeNull();
  });

  it("não compra carta e preserva mãos e Decks Principais", () => {
    const prepared = createPreparedDuel();
    const started = startFirstTurn(prepared, gxLegacyProfile);

    for (const playerIndex of [0, 1] as const) {
      expect(started.players[playerIndex].hand).toEqual(
        prepared.players[playerIndex].hand,
      );
      expect(started.players[playerIndex].hand).toHaveLength(
        gxLegacyProfile.startingHandSize,
      );
      expect(started.players[playerIndex].mainDeck).toEqual(
        prepared.players[playerIndex].mainDeck,
      );
    }
  });

  it("preserva todas as áreas e propriedades dos jogadores", () => {
    const prepared = createPreparedDuel();
    const started = startFirstTurn(prepared, gxLegacyProfile);

    expect(started.players).toEqual(prepared.players);
  });

  it("preserva seed, state e calls do RNG sem consumir chamadas", () => {
    const prepared = createPreparedDuel();
    const started = startFirstTurn(prepared, gxLegacyProfile);

    expect(started.rngState).toEqual(prepared.rngState);
    expect(started.rngState?.seed).toBe(prepared.rngState?.seed);
    expect(started.rngState?.state).toBe(prepared.rngState?.state);
    expect(started.rngState?.calls).toBe(prepared.rngState?.calls);
  });

  it("preserva identificadores, versões e turnOrder", () => {
    const prepared = createPreparedDuel("player-2");
    const started = startFirstTurn(prepared, gxLegacyProfile);

    expect(started.duelId).toBe(prepared.duelId);
    expect(started.rulesProfileId).toBe(prepared.rulesProfileId);
    expect(started.engineVersion).toBe(prepared.engineVersion);
    expect(started.cardPoolVersion).toBe(prepared.cardPoolVersion);
    expect(started.turnOrder).toEqual(prepared.turnOrder);
  });

  it("não modifica DuelState, RulesProfile, RNG ou jogadores recebidos", () => {
    const prepared = createPreparedDuel();
    const duelSnapshot = structuredClone(prepared);
    const profileSnapshot = structuredClone(gxLegacyProfile);
    const playerReferences = [...prepared.players];
    const rngReference = prepared.rngState;

    startFirstTurn(prepared, gxLegacyProfile);

    expect(prepared).toEqual(duelSnapshot);
    expect(gxLegacyProfile).toEqual(profileSnapshot);
    expect(prepared.players[0]).toBe(playerReferences[0]);
    expect(prepared.players[1]).toBe(playerReferences[1]);
    expect(prepared.rngState).toBe(rngReference);
  });

  it("retorna um novo DuelState com cópias defensivas", () => {
    const prepared = createPreparedDuel();
    const started = startFirstTurn(prepared, gxLegacyProfile);

    expect(started).not.toBe(prepared);
    expect(started.players).not.toBe(prepared.players);
    expect(started.turnOrder).not.toBe(prepared.turnOrder);
    expect(started.rngState).not.toBe(prepared.rngState);

    for (const playerIndex of [0, 1] as const) {
      expect(started.players[playerIndex]).not.toBe(
        prepared.players[playerIndex],
      );

      const inputArrays = getPlayerArrays(prepared.players[playerIndex]);
      const outputArrays = getPlayerArrays(started.players[playerIndex]);

      for (const [index, inputArray] of inputArrays.entries()) {
        expect(outputArrays[index]).not.toBe(inputArray);
      }
    }
  });

  it("duas chamadas sobre entradas independentes não compartilham arrays", () => {
    const first = startFirstTurn(createPreparedDuel(), gxLegacyProfile);
    const second = startFirstTurn(createPreparedDuel(), gxLegacyProfile);

    expect(first.players).not.toBe(second.players);
    expect(first.turnOrder).not.toBe(second.turnOrder);

    for (const playerIndex of [0, 1] as const) {
      expect(first.players[playerIndex]).not.toBe(second.players[playerIndex]);

      const firstArrays = getPlayerArrays(first.players[playerIndex]);
      const secondArrays = getPlayerArrays(second.players[playerIndex]);

      for (const [index, firstArray] of firstArrays.entries()) {
        expect(secondArrays[index]).not.toBe(firstArray);
      }
    }
  });

  it("retorna estado, RNG, jogadores e arrays congelados", () => {
    const started = startFirstTurn(createPreparedDuel(), gxLegacyProfile);

    expect(Object.isFrozen(started)).toBe(true);
    expect(Object.isFrozen(started.players)).toBe(true);
    expect(Object.isFrozen(started.turnOrder)).toBe(true);
    expect(Object.isFrozen(started.rngState)).toBe(true);

    for (const player of started.players) {
      expect(Object.isFrozen(player)).toBe(true);
      for (const array of getPlayerArrays(player)) {
        expect(Object.isFrozen(array)).toBe(true);
      }
    }
  });

  it.each(["ACTIVE", "FINISHED"] satisfies DuelStatus[])(
    "rejeita status %s",
    (status) => {
      const invalid: DuelState = { ...createPreparedDuel(), status };

      expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow(
        "Somente um Duelo em PREPARING pode ser iniciado.",
      );
    },
  );

  it("rejeita DuelState sem rngState", () => {
    const invalid: DuelState = { ...createPreparedDuel(), rngState: null };

    expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow(
      "O Duelo deve possuir um estado de RNG.",
    );
  });

  it("rejeita turnNumber diferente de zero", () => {
    const invalid: DuelState = { ...createPreparedDuel(), turnNumber: 1 };

    expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow(
      "O Duelo preparado deve estar antes do primeiro turno.",
    );
  });

  it("rejeita currentPlayerId já definido", () => {
    const invalid: DuelState = {
      ...createPreparedDuel(),
      currentPlayerId: "player-1",
    };

    expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow(
      "O Duelo preparado não pode possuir jogador atual.",
    );
  });

  it("rejeita phase já definida", () => {
    const phase: DuelPhase = "STANDBY";
    const invalid: DuelState = { ...createPreparedDuel(), phase };

    expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow(
      "O Duelo preparado não pode possuir fase atual.",
    );
  });

  it("rejeita winnerId já definido", () => {
    const invalid: DuelState = {
      ...createPreparedDuel(),
      winnerId: "player-2",
    };

    expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow(
      "O Duelo preparado não pode possuir vencedor.",
    );
  });

  it("rejeita resultReason já definido", () => {
    const invalid: DuelState = {
      ...createPreparedDuel(),
      resultReason: "INVALID_RESULT",
    };

    expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow(
      "O Duelo preparado não pode possuir motivo de resultado.",
    );
  });

  it("rejeita RulesProfile inválido", () => {
    const invalidProfile: RulesProfile = {
      ...gxLegacyProfile,
      startingLifePoints: 0,
    };

    expect(() => startFirstTurn(createPreparedDuel(), invalidProfile)).toThrow(
      "RulesProfile inválido.",
    );
  });

  it("rejeita RulesProfile incompatível", () => {
    const incompatibleProfile: RulesProfile = {
      ...gxLegacyProfile,
      id: "OTHER_PROFILE",
    };

    expect(() =>
      startFirstTurn(createPreparedDuel(), incompatibleProfile),
    ).toThrow("RulesProfile incompatível com o Duelo.");
  });

  it.each([1, 3])("rejeita quantidade de jogadores igual a %i", (amount) => {
    const prepared = createPreparedDuel();
    const players = [...prepared.players, createPlayer("player-3")].slice(
      0,
      amount,
    );
    const invalid = { ...prepared, players } as unknown as DuelState;

    expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow(
      "O Duelo deve possuir exatamente dois jogadores.",
    );
  });

  it("rejeita jogadores com IDs iguais", () => {
    const prepared = createPreparedDuel();
    const duplicateIdPlayer: DuelPlayerState = {
      ...prepared.players[1],
      playerId: prepared.players[0].playerId,
    };
    const invalid: DuelState = {
      ...prepared,
      players: [prepared.players[0], duplicateIdPlayer],
    };

    expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow(
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
      ...createPreparedDuel(),
      turnOrder,
    } as unknown as DuelState;

    expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow(/turnOrder/);
  });

  it.each(["monsterZones", "spellTrapZones"] as const)(
    "rejeita quantidade incompatível em %s",
    (zoneField) => {
      const prepared = createPreparedDuel();
      const invalidPlayer: DuelPlayerState = {
        ...prepared.players[0],
        [zoneField]: [],
      };
      const invalid: DuelState = {
        ...prepared,
        players: [invalidPlayer, prepared.players[1]],
      };

      expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow(
        "As zonas do jogador são incompatíveis com o perfil.",
      );
    },
  );

  it.each([4, 6])("rejeita mão inicial com %i cartas", (handSize) => {
    const prepared = createPreparedDuel();
    const invalidPlayer: DuelPlayerState = {
      ...prepared.players[0],
      hand:
        handSize < prepared.players[0].hand.length
          ? prepared.players[0].hand.slice(0, handSize)
          : [...prepared.players[0].hand, "extra-hand-card"],
    };
    const invalid: DuelState = {
      ...prepared,
      players: [invalidPlayer, prepared.players[1]],
    };

    expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow(
      "A mão inicial é incompatível com o perfil.",
    );
  });

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
    const prepared = createPreparedDuel();
    const duplicateId = prepared.players[0].hand[0]!;
    const invalidSecondPlayer = withDuplicateInArea(
      prepared.players[1],
      area,
      duplicateId,
    );
    const invalid: DuelState = {
      ...prepared,
      players: [prepared.players[0], invalidSecondPlayer],
    };

    expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow(
      "IDs de instância de carta devem ser únicos no Duelo.",
    );
  });

  it("erro não modifica a entrada", () => {
    const prepared = createPreparedDuel();
    const invalid: DuelState = { ...prepared, turnNumber: 2 };
    const snapshot = structuredClone(invalid);

    expect(() => startFirstTurn(invalid, gxLegacyProfile)).toThrow();
    expect(invalid).toEqual(snapshot);
  });
});
