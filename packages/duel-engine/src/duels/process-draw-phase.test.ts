import { describe, expect, it } from "vitest";

import {
  createDeterministicRng,
  createInitialPlayerState,
  gxLegacyProfile,
  processDrawPhase,
  type DuelPlayerState,
  type DuelResultReason,
  type DuelState,
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
    [`${playerId}-top`, `${playerId}-second`, `${playerId}-third`],
    [`${playerId}-extra-down`],
  );

  return {
    ...player,
    hand: [`${playerId}-hand-1`, `${playerId}-hand-2`],
    graveyard: [`${playerId}-graveyard`],
    banishedFaceUp: [`${playerId}-banished-up`],
    banishedFaceDown: [`${playerId}-banished-down`],
    extraDeckFaceUp: [`${playerId}-extra-up`],
    monsterZones: [`${playerId}-monster`, null, null, null, null],
    spellTrapZones: [null, `${playerId}-spell`, null, null, null],
    fieldZone: `${playerId}-field`,
  };
}

function createActiveDuel(
  turnNumber = 1,
  firstPlayerId: PlayerId = "player-1",
): DuelState {
  const secondPlayerId = firstPlayerId === "player-1" ? "player-2" : "player-1";
  const turnOrder: [PlayerId, PlayerId] = [firstPlayerId, secondPlayerId];

  return {
    duelId: "duel-draw",
    rulesProfileId: gxLegacyProfile.id,
    engineVersion: "engine-1",
    cardPoolVersion: "pool-1",
    players: [createPlayer("player-1"), createPlayer("player-2")],
    turnOrder,
    rngState: createDeterministicRng("draw-phase-seed"),
    status: "ACTIVE",
    turnNumber,
    currentPlayerId: turnOrder[(turnNumber - 1) % 2]!,
    phase: "DRAW",
    winnerId: null,
    resultReason: null,
  };
}

function replacePlayer(
  duelState: DuelState,
  playerId: PlayerId,
  change: (player: DuelPlayerState) => DuelPlayerState,
): DuelState {
  const players = duelState.players.map((player) =>
    player.playerId === playerId ? change(player) : player,
  );

  return { ...duelState, players } as unknown as DuelState;
}

function currentPlayer(duelState: DuelState): DuelPlayerState {
  const player = duelState.players.find(
    ({ playerId }) => playerId === duelState.currentPlayerId,
  );

  if (!player) {
    throw new Error("Jogador atual ausente no fixture.");
  }

  return player;
}

function otherPlayer(duelState: DuelState): DuelPlayerState {
  const player = duelState.players.find(
    ({ playerId }) => playerId !== duelState.currentPlayerId,
  );

  if (!player) {
    throw new Error("Outro jogador ausente no fixture.");
  }

  return player;
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

  return {
    ...player,
    [area]: [duplicateId, ...player[area].slice(1)],
  };
}

const drawOnFirstTurnProfile: RulesProfile = Object.freeze({
  ...gxLegacyProfile,
  drawOnFirstTurn: true,
});

describe("processDrawPhase", () => {
  it("primeiro turno GX_LEGACY não compra", () => {
    const duel = createActiveDuel();
    const result = processDrawPhase(duel, gxLegacyProfile);

    expect(currentPlayer(result).hand).toEqual(currentPlayer(duel).hand);
  });

  it("primeiro turno GX_LEGACY avança para STANDBY", () => {
    expect(processDrawPhase(createActiveDuel(), gxLegacyProfile).phase).toBe(
      "STANDBY",
    );
  });

  it("primeiro turno GX_LEGACY permanece ACTIVE e não consome RNG", () => {
    const duel = createActiveDuel();
    const result = processDrawPhase(duel, gxLegacyProfile);

    expect(result.status).toBe("ACTIVE");
    expect(result.rngState).toEqual(duel.rngState);
  });

  it("primeiro turno preserva mão e mainDeck", () => {
    const duel = createActiveDuel();
    const result = processDrawPhase(duel, gxLegacyProfile);

    expect(currentPlayer(result).hand).toEqual(currentPlayer(duel).hand);
    expect(currentPlayer(result).mainDeck).toEqual(
      currentPlayer(duel).mainDeck,
    );
  });

  it("primeiro turno com Deck vazio não causa derrota", () => {
    const duel = replacePlayer(createActiveDuel(), "player-1", (player) => ({
      ...player,
      mainDeck: [],
    }));
    const result = processDrawPhase(duel, gxLegacyProfile);

    expect(result.status).toBe("ACTIVE");
    expect(result.phase).toBe("STANDBY");
    expect(result.winnerId).toBeNull();
    expect(result.resultReason).toBeNull();
  });

  it("perfil válido com drawOnFirstTurn true compra no turno 1", () => {
    const duel = createActiveDuel();
    const result = processDrawPhase(duel, drawOnFirstTurnProfile);

    expect(currentPlayer(result).hand).toHaveLength(
      currentPlayer(duel).hand.length + 1,
    );
  });

  it("turno 2 compra exatamente uma carta", () => {
    const duel = createActiveDuel(2);
    const result = processDrawPhase(duel, gxLegacyProfile);

    expect(currentPlayer(result).hand).toHaveLength(
      currentPlayer(duel).hand.length + 1,
    );
    expect(currentPlayer(result).mainDeck).toHaveLength(
      currentPlayer(duel).mainDeck.length - 1,
    );
  });

  it("turno posterior compra exatamente uma carta", () => {
    const duel = createActiveDuel(7);
    const result = processDrawPhase(duel, gxLegacyProfile);

    expect(currentPlayer(result).hand).toHaveLength(
      currentPlayer(duel).hand.length + 1,
    );
  });

  it("compra a carta do índice 0", () => {
    const duel = createActiveDuel(2);
    const result = processDrawPhase(duel, gxLegacyProfile);

    expect(currentPlayer(result).hand.at(-1)).toBe(
      currentPlayer(duel).mainDeck[0],
    );
  });

  it("remove exatamente a carta comprada do mainDeck", () => {
    const duel = createActiveDuel(2);
    const result = processDrawPhase(duel, gxLegacyProfile);

    expect(currentPlayer(result).mainDeck).toEqual(
      currentPlayer(duel).mainDeck.slice(1),
    );
  });

  it("adiciona a carta ao final da mão", () => {
    const duel = createActiveDuel(2);
    const before = currentPlayer(duel);
    const result = processDrawPhase(duel, gxLegacyProfile);

    expect(currentPlayer(result).hand).toEqual([
      ...before.hand,
      before.mainDeck[0],
    ]);
  });

  it("preserva o outro jogador", () => {
    const duel = createActiveDuel(2);
    const result = processDrawPhase(duel, gxLegacyProfile);

    expect(otherPlayer(result)).toEqual(otherPlayer(duel));
  });

  it("avança para STANDBY após compra", () => {
    expect(processDrawPhase(createActiveDuel(2), gxLegacyProfile).phase).toBe(
      "STANDBY",
    );
  });

  it("mantém status ACTIVE após compra", () => {
    expect(processDrawPhase(createActiveDuel(2), gxLegacyProfile).status).toBe(
      "ACTIVE",
    );
  });

  it("preserva turnNumber", () => {
    const duel = createActiveDuel(8);

    expect(processDrawPhase(duel, gxLegacyProfile).turnNumber).toBe(8);
  });

  it("preserva currentPlayerId", () => {
    const duel = createActiveDuel(2);

    expect(processDrawPhase(duel, gxLegacyProfile).currentPlayerId).toBe(
      duel.currentPlayerId,
    );
  });

  it("não modifica rngState.calls", () => {
    const duel = createActiveDuel(2);

    expect(processDrawPhase(duel, gxLegacyProfile).rngState?.calls).toBe(
      duel.rngState?.calls,
    );
  });

  it("preserva seed e state do RNG", () => {
    const duel = createActiveDuel(2);
    const result = processDrawPhase(duel, gxLegacyProfile);

    expect(result.rngState?.seed).toBe(duel.rngState?.seed);
    expect(result.rngState?.state).toBe(duel.rngState?.state);
  });

  it("não modifica DuelState recebido", () => {
    const duel = createActiveDuel(2);
    const snapshot = structuredClone(duel);

    processDrawPhase(duel, gxLegacyProfile);

    expect(duel).toEqual(snapshot);
  });

  it("não modifica RulesProfile", () => {
    const profile = {
      ...gxLegacyProfile,
      enabledSummons: [...gxLegacyProfile.enabledSummons],
    };
    const snapshot = structuredClone(profile);

    processDrawPhase(createActiveDuel(2), profile);

    expect(profile).toEqual(snapshot);
  });

  it("saída não compartilha arrays internos com a entrada", () => {
    const duel = createActiveDuel(2);
    const result = processDrawPhase(duel, gxLegacyProfile);

    expect(result.players).not.toBe(duel.players);
    expect(result.turnOrder).not.toBe(duel.turnOrder);
    expect(result.rngState).not.toBe(duel.rngState);

    for (const playerIndex of [0, 1] as const) {
      expect(result.players[playerIndex]).not.toBe(duel.players[playerIndex]);

      const inputArrays = getPlayerArrays(duel.players[playerIndex]);
      const outputArrays = getPlayerArrays(result.players[playerIndex]);

      for (const [index, inputArray] of inputArrays.entries()) {
        expect(outputArrays[index]).not.toBe(inputArray);
      }
    }
  });

  it("duas execuções independentes não compartilham arrays", () => {
    const first = processDrawPhase(createActiveDuel(2), gxLegacyProfile);
    const second = processDrawPhase(createActiveDuel(2), gxLegacyProfile);

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

  it("congela estado, jogadores, RNG e todos os arrays da saída", () => {
    const result = processDrawPhase(createActiveDuel(2), gxLegacyProfile);

    expect(Object.isFrozen(result)).toBe(true);
    expect(Object.isFrozen(result.players)).toBe(true);
    expect(Object.isFrozen(result.turnOrder)).toBe(true);
    expect(Object.isFrozen(result.rngState)).toBe(true);

    for (const player of result.players) {
      expect(Object.isFrozen(player)).toBe(true);
      for (const array of getPlayerArrays(player)) {
        expect(Object.isFrozen(array)).toBe(true);
      }
    }
  });

  it("compra obrigatória com Deck vazio encerra o Duelo", () => {
    const duel = replacePlayer(createActiveDuel(2), "player-2", (player) => ({
      ...player,
      mainDeck: [],
    }));

    expect(processDrawPhase(duel, gxLegacyProfile).status).toBe("FINISHED");
  });

  it("Deck vazio mantém phase DRAW", () => {
    const duel = replacePlayer(createActiveDuel(2), "player-2", (player) => ({
      ...player,
      mainDeck: [],
    }));

    expect(processDrawPhase(duel, gxLegacyProfile).phase).toBe("DRAW");
  });

  it("Deck vazio mantém o jogador derrotado como currentPlayerId", () => {
    const duel = replacePlayer(createActiveDuel(2), "player-2", (player) => ({
      ...player,
      mainDeck: [],
    }));

    expect(processDrawPhase(duel, gxLegacyProfile).currentPlayerId).toBe(
      "player-2",
    );
  });

  it("Deck vazio preserva turnNumber", () => {
    const duel = replacePlayer(createActiveDuel(2), "player-2", (player) => ({
      ...player,
      mainDeck: [],
    }));

    expect(processDrawPhase(duel, gxLegacyProfile).turnNumber).toBe(2);
  });

  it("Deck vazio define o outro jogador como winnerId", () => {
    const duel = replacePlayer(createActiveDuel(2), "player-2", (player) => ({
      ...player,
      mainDeck: [],
    }));

    expect(processDrawPhase(duel, gxLegacyProfile).winnerId).toBe("player-1");
  });

  it("Deck vazio define resultReason como DECK_OUT", () => {
    const duel = replacePlayer(createActiveDuel(2), "player-2", (player) => ({
      ...player,
      mainDeck: [],
    }));
    const reason: DuelResultReason = "DECK_OUT";

    expect(processDrawPhase(duel, gxLegacyProfile).resultReason).toBe(reason);
  });

  it("Deck vazio não modifica mãos ou Decks", () => {
    const duel = replacePlayer(createActiveDuel(2), "player-2", (player) => ({
      ...player,
      mainDeck: [],
    }));
    const result = processDrawPhase(duel, gxLegacyProfile);

    for (const playerIndex of [0, 1] as const) {
      expect(result.players[playerIndex].hand).toEqual(
        duel.players[playerIndex].hand,
      );
      expect(result.players[playerIndex].mainDeck).toEqual(
        duel.players[playerIndex].mainDeck,
      );
    }
  });

  it("Deck vazio não consome RNG", () => {
    const duel = replacePlayer(createActiveDuel(2), "player-2", (player) => ({
      ...player,
      mainDeck: [],
    }));

    expect(processDrawPhase(duel, gxLegacyProfile).rngState).toEqual(
      duel.rngState,
    );
  });

  it("funciona quando o jogador atual é players[1]", () => {
    const duel = createActiveDuel(1, "player-2");
    const result = processDrawPhase(duel, drawOnFirstTurnProfile);

    expect(result.players[1].hand).toEqual([
      ...duel.players[1].hand,
      duel.players[1].mainDeck[0],
    ]);
    expect(result.players[0]).toEqual(duel.players[0]);
  });

  it.each(["PREPARING", "FINISHED"] as const)("rejeita status %s", (status) => {
    const invalid: DuelState = { ...createActiveDuel(), status };

    expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
      "Somente um Duelo ACTIVE pode processar a Fase de Compra.",
    );
  });

  it.each(["STANDBY", "MAIN_1", "BATTLE", "MAIN_2", "END"] as const)(
    "rejeita phase %s",
    (phase) => {
      const invalid: DuelState = { ...createActiveDuel(), phase };

      expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
        "O Duelo deve estar na fase DRAW.",
      );
    },
  );

  it.each([0, -1, -10, 1.5])("rejeita turnNumber inválido %s", (turnNumber) => {
    const invalid: DuelState = { ...createActiveDuel(), turnNumber };

    expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
      "turnNumber deve ser um inteiro maior ou igual a 1.",
    );
  });

  it("rejeita currentPlayerId null", () => {
    const invalid: DuelState = {
      ...createActiveDuel(),
      currentPlayerId: null,
    };

    expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
      "A Fase de Compra deve possuir jogador atual.",
    );
  });

  it("rejeita jogador atual desconhecido", () => {
    const invalid: DuelState = {
      ...createActiveDuel(),
      currentPlayerId: "unknown-player",
    };

    expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
      "currentPlayerId é incompatível com turnOrder e com os jogadores.",
    );
  });

  it("rejeita currentPlayerId incompatível com o turno", () => {
    const invalid: DuelState = {
      ...createActiveDuel(2),
      currentPlayerId: "player-1",
    };

    expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
      "currentPlayerId é incompatível com turnOrder e com os jogadores.",
    );
  });

  it("rejeita vencedor já definido", () => {
    const invalid: DuelState = {
      ...createActiveDuel(),
      winnerId: "player-2",
    };

    expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
      "Um Duelo ACTIVE não pode possuir vencedor.",
    );
  });

  it("rejeita motivo de resultado já definido", () => {
    const invalid: DuelState = {
      ...createActiveDuel(),
      resultReason: "DECK_OUT",
    };

    expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
      "Um Duelo ACTIVE não pode possuir motivo de resultado.",
    );
  });

  it("rejeita rngState null", () => {
    const invalid: DuelState = { ...createActiveDuel(), rngState: null };

    expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
      "O Duelo deve possuir um estado de RNG.",
    );
  });

  it("rejeita perfil inválido", () => {
    const invalidProfile: RulesProfile = {
      ...gxLegacyProfile,
      startingLifePoints: 0,
    };

    expect(() => processDrawPhase(createActiveDuel(), invalidProfile)).toThrow(
      "RulesProfile inválido.",
    );
  });

  it("rejeita perfil incompatível", () => {
    const incompatibleProfile: RulesProfile = {
      ...gxLegacyProfile,
      id: "OTHER_PROFILE",
    };

    expect(() =>
      processDrawPhase(createActiveDuel(), incompatibleProfile),
    ).toThrow("RulesProfile incompatível com o Duelo.");
  });

  it.each([1, 3])("rejeita quantidade de jogadores igual a %i", (amount) => {
    const duel = createActiveDuel();
    const players = [...duel.players, createPlayer("player-3")].slice(
      0,
      amount,
    );
    const invalid = { ...duel, players } as unknown as DuelState;

    expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
      "O Duelo deve possuir exatamente dois jogadores.",
    );
  });

  it("rejeita jogadores com IDs iguais", () => {
    const duel = createActiveDuel();
    const duplicateIdPlayer: DuelPlayerState = {
      ...duel.players[1],
      playerId: duel.players[0].playerId,
    };
    const invalid: DuelState = {
      ...duel,
      players: [duel.players[0], duplicateIdPlayer],
    };

    expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
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
      ...createActiveDuel(),
      turnOrder,
    } as unknown as DuelState;

    expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
      /turnOrder/,
    );
  });

  it.each(["monsterZones", "spellTrapZones"] as const)(
    "rejeita quantidade incompatível em %s",
    (zoneField) => {
      const duel = createActiveDuel();
      const invalidPlayer: DuelPlayerState = {
        ...duel.players[0],
        [zoneField]: [],
      };
      const invalid: DuelState = {
        ...duel,
        players: [invalidPlayer, duel.players[1]],
      };

      expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
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
    const duel = createActiveDuel();
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

    expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow(
      "IDs de instância de carta devem ser únicos no Duelo.",
    );
  });

  it("erro não modifica a entrada", () => {
    const invalid: DuelState = { ...createActiveDuel(), turnNumber: 0 };
    const snapshot = structuredClone(invalid);

    expect(() => processDrawPhase(invalid, gxLegacyProfile)).toThrow();
    expect(invalid).toEqual(snapshot);
  });
});
