import { describe, expect, it } from "vitest";

import {
  gxLegacyProfile,
  startNextTurn,
  transitionFromMainPhaseOne,
  type DuelPlayerState,
  type DuelState,
  type PlayerId,
  type RulesProfile,
} from "../index.js";
import {
  createMainPhaseOneDuel,
  createMainPhaseOnePlayer,
  PLAYER_ARRAY_FIELDS,
  type CardArea,
  withDuplicateInArea,
} from "./main-phase-one-test-helpers.js";

function createEndPhaseDuel(turnNumber = 1): DuelState {
  return transitionFromMainPhaseOne(
    createMainPhaseOneDuel(turnNumber),
    gxLegacyProfile,
    "END",
  );
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

function findPlayer(duelState: DuelState, playerId: PlayerId): DuelPlayerState {
  const player = duelState.players.find(
    (candidate) => candidate.playerId === playerId,
  );

  if (!player) {
    throw new Error("Jogador ausente no fixture.");
  }

  return player;
}

function withNormalSummonsUsed(
  duelState: DuelState,
  playerOneUsed: number,
  playerTwoUsed: number,
): DuelState {
  return {
    ...duelState,
    players: duelState.players.map((player) => ({
      ...player,
      normalSummonsUsed:
        player.playerId === "player-1" ? playerOneUsed : playerTwoUsed,
    })),
  } as unknown as DuelState;
}

describe("startNextTurn", () => {
  it.each([
    [1, 2, "player-2"],
    [2, 3, "player-1"],
    [7, 8, "player-2"],
    [8, 9, "player-1"],
  ] as const)(
    "encerra o turno %i e inicia o turno %i com %s",
    (turnNumber, expectedTurnNumber, expectedPlayerId) => {
      const result = startNextTurn(
        createEndPhaseDuel(turnNumber),
        gxLegacyProfile,
      );

      expect(result.turnNumber).toBe(expectedTurnNumber);
      expect(result.currentPlayerId).toBe(expectedPlayerId);
    },
  );

  it("usa turnOrder mesmo quando a ordem física de players é diferente", () => {
    const duel = createEndPhaseDuel();
    const reversedPlayers: [DuelPlayerState, DuelPlayerState] = [
      duel.players[1],
      duel.players[0],
    ];
    const reordered: DuelState = { ...duel, players: reversedPlayers };
    const result = startNextTurn(reordered, gxLegacyProfile);

    expect(reordered.turnOrder).toEqual(["player-1", "player-2"]);
    expect(reordered.players.map(({ playerId }) => playerId)).toEqual([
      "player-2",
      "player-1",
    ]);
    expect(result.currentPlayerId).toBe("player-2");
    expect(result.players.map(({ playerId }) => playerId)).toEqual([
      "player-2",
      "player-1",
    ]);
  });

  it("incrementa turnNumber exatamente uma vez e define phase DRAW", () => {
    const duel = createEndPhaseDuel(12);
    const result = startNextTurn(duel, gxLegacyProfile);

    expect(result.turnNumber).toBe(duel.turnNumber + 1);
    expect(result.phase).toBe("DRAW");
  });

  it("mantém o Duelo ACTIVE, sem vencedor e sem motivo de resultado", () => {
    const result = startNextTurn(createEndPhaseDuel(), gxLegacyProfile);

    expect(result.status).toBe("ACTIVE");
    expect(result.winnerId).toBeNull();
    expect(result.resultReason).toBeNull();
  });

  it("zera normalSummonsUsed somente no jogador que inicia o novo turno", () => {
    const duel = withNormalSummonsUsed(createEndPhaseDuel(), 3, 4);
    const result = startNextTurn(duel, gxLegacyProfile);

    expect(findPlayer(result, "player-2").normalSummonsUsed).toBe(0);
    expect(findPlayer(result, "player-1").normalSummonsUsed).toBe(3);
  });

  it("reseta por playerId, não pela posição no array players", () => {
    const duel = withNormalSummonsUsed(createEndPhaseDuel(), 3, 4);
    const reversed: DuelState = {
      ...duel,
      players: [duel.players[1], duel.players[0]],
    };
    const result = startNextTurn(reversed, gxLegacyProfile);

    expect(result.players[0].playerId).toBe("player-2");
    expect(result.players[0].normalSummonsUsed).toBe(0);
    expect(result.players[1].normalSummonsUsed).toBe(3);
  });

  it("preserva normalSummonLimit dos dois jogadores", () => {
    const duel = createEndPhaseDuel();
    const result = startNextTurn(duel, gxLegacyProfile);

    for (const playerId of duel.turnOrder) {
      expect(findPlayer(result, playerId).normalSummonLimit).toBe(
        findPlayer(duel, playerId).normalSummonLimit,
      );
    }
  });

  it("não compra, remove ou reordena cartas e preserva todas as áreas", () => {
    const duel = createEndPhaseDuel();
    const result = startNextTurn(duel, gxLegacyProfile);

    for (const playerIndex of [0, 1] as const) {
      const inputPlayer = duel.players[playerIndex];
      const outputPlayer = result.players[playerIndex];

      for (const field of PLAYER_ARRAY_FIELDS) {
        expect(outputPlayer[field]).toEqual(inputPlayer[field]);
      }
      expect(outputPlayer.fieldZone).toBe(inputPlayer.fieldZone);
      expect(outputPlayer.mainDeck).toEqual(inputPlayer.mainDeck);
      expect(outputPlayer.hand).toEqual(inputPlayer.hand);
    }
  });

  it("preserva Pontos de Vida", () => {
    const duel = createEndPhaseDuel();
    const result = startNextTurn(duel, gxLegacyProfile);

    for (const playerIndex of [0, 1] as const) {
      expect(result.players[playerIndex].lifePoints).toBe(
        duel.players[playerIndex].lifePoints,
      );
    }
  });

  it("preserva seed, state e calls do RNG sem consumo e cria cópia congelada", () => {
    const duel = createEndPhaseDuel();
    const result = startNextTurn(duel, gxLegacyProfile);

    expect(result.rngState).toEqual(duel.rngState);
    expect(result.rngState?.seed).toBe(duel.rngState?.seed);
    expect(result.rngState?.state).toBe(duel.rngState?.state);
    expect(result.rngState?.calls).toBe(duel.rngState?.calls);
    expect(result.rngState).not.toBe(duel.rngState);
    expect(Object.isFrozen(result.rngState)).toBe(true);
  });

  it("preserva identificadores, versões e turnOrder", () => {
    const duel = createEndPhaseDuel();
    const result = startNextTurn(duel, gxLegacyProfile);

    expect(result.duelId).toBe(duel.duelId);
    expect(result.rulesProfileId).toBe(duel.rulesProfileId);
    expect(result.engineVersion).toBe(duel.engineVersion);
    expect(result.cardPoolVersion).toBe(duel.cardPoolVersion);
    expect(result.turnOrder).toEqual(duel.turnOrder);
  });

  it("não modifica DuelState, RulesProfile, jogadores, arrays ou RNG recebidos", () => {
    const duel = withNormalSummonsUsed(createEndPhaseDuel(), 3, 4);
    const profile: RulesProfile = {
      ...gxLegacyProfile,
      enabledSummons: [...gxLegacyProfile.enabledSummons],
    };
    const duelSnapshot = structuredClone(duel);
    const profileSnapshot = structuredClone(profile);

    startNextTurn(duel, profile);

    expect(duel).toEqual(duelSnapshot);
    expect(profile).toEqual(profileSnapshot);
  });

  it("não compartilha objetos ou arrays mutáveis com a entrada", () => {
    const duel = createEndPhaseDuel();
    const result = startNextTurn(duel, gxLegacyProfile);

    expect(result.players).not.toBe(duel.players);
    expect(result.turnOrder).not.toBe(duel.turnOrder);
    expect(result.rngState).not.toBe(duel.rngState);

    for (const playerIndex of [0, 1] as const) {
      expect(result.players[playerIndex]).not.toBe(duel.players[playerIndex]);
      for (const field of PLAYER_ARRAY_FIELDS) {
        expect(result.players[playerIndex][field]).not.toBe(
          duel.players[playerIndex][field],
        );
      }
    }
  });

  it("congela DuelState, jogadores, arrays, turnOrder e rngState retornados", () => {
    const result = startNextTurn(createEndPhaseDuel(), gxLegacyProfile);

    expect(Object.isFrozen(result)).toBe(true);
    expect(Object.isFrozen(result.players)).toBe(true);
    expect(Object.isFrozen(result.turnOrder)).toBe(true);
    expect(Object.isFrozen(result.rngState)).toBe(true);

    for (const player of result.players) {
      expect(Object.isFrozen(player)).toBe(true);
      for (const field of PLAYER_ARRAY_FIELDS) {
        expect(Object.isFrozen(player[field])).toBe(true);
      }
    }
  });

  it("duas execuções independentes não compartilham estruturas", () => {
    const duel = createEndPhaseDuel();
    const first = startNextTurn(duel, gxLegacyProfile);
    const second = startNextTurn(duel, gxLegacyProfile);

    expect(first).not.toBe(second);
    expect(first.players).not.toBe(second.players);
    expect(first.turnOrder).not.toBe(second.turnOrder);
    expect(first.rngState).not.toBe(second.rngState);

    for (const playerIndex of [0, 1] as const) {
      expect(first.players[playerIndex]).not.toBe(second.players[playerIndex]);
      for (const field of PLAYER_ARRAY_FIELDS) {
        expect(first.players[playerIndex][field]).not.toBe(
          second.players[playerIndex][field],
        );
      }
    }
  });

  it.each(["PREPARING", "FINISHED"] as const)("rejeita status %s", (status) => {
    const invalid: DuelState = { ...createEndPhaseDuel(), status };

    expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(
      "Somente um Duelo ACTIVE pode iniciar o próximo turno.",
    );
  });

  it.each(["DRAW", "STANDBY", "MAIN_1", "BATTLE", "MAIN_2", null] as const)(
    "rejeita phase diferente de END: %s",
    (phase) => {
      const invalid: DuelState = { ...createEndPhaseDuel(), phase };

      expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(
        "O Duelo deve estar na fase END.",
      );
    },
  );

  it.each([0, -1, -10, 1.5])("rejeita turnNumber inválido %s", (turnNumber) => {
    const invalid: DuelState = { ...createEndPhaseDuel(), turnNumber };

    expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(
      "turnNumber deve ser um inteiro maior ou igual a 1.",
    );
  });

  it("rejeita currentPlayerId null", () => {
    const invalid: DuelState = {
      ...createEndPhaseDuel(),
      currentPlayerId: null,
    };

    expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(
      "A Fase Final deve possuir jogador atual.",
    );
  });

  it("rejeita currentPlayerId inexistente", () => {
    const invalid: DuelState = {
      ...createEndPhaseDuel(),
      currentPlayerId: "unknown-player",
    };

    expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(
      "currentPlayerId é incompatível com turnOrder e com os jogadores.",
    );
  });

  it("rejeita currentPlayerId incompatível com turnOrder e turnNumber", () => {
    const invalid: DuelState = {
      ...createEndPhaseDuel(2),
      currentPlayerId: "player-1",
    };

    expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(
      "currentPlayerId é incompatível com turnOrder e com os jogadores.",
    );
  });

  it("rejeita winnerId já definido", () => {
    const invalid: DuelState = {
      ...createEndPhaseDuel(),
      winnerId: "player-2",
    };

    expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(
      "Um Duelo ACTIVE não pode possuir vencedor.",
    );
  });

  it("rejeita resultReason já definido", () => {
    const invalid: DuelState = {
      ...createEndPhaseDuel(),
      resultReason: "DECK_OUT",
    };

    expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(
      "Um Duelo ACTIVE não pode possuir motivo de resultado.",
    );
  });

  it("rejeita rngState null", () => {
    const invalid: DuelState = { ...createEndPhaseDuel(), rngState: null };

    expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(
      "O Duelo deve possuir um estado de RNG.",
    );
  });

  it("rejeita RulesProfile inválido", () => {
    const invalidProfile: RulesProfile = {
      ...gxLegacyProfile,
      startingLifePoints: 0,
    };

    expect(() => startNextTurn(createEndPhaseDuel(), invalidProfile)).toThrow(
      "RulesProfile inválido.",
    );
  });

  it("rejeita RulesProfile com ID incompatível", () => {
    const incompatibleProfile: RulesProfile = {
      ...gxLegacyProfile,
      id: "OTHER_PROFILE",
    };

    expect(() =>
      startNextTurn(createEndPhaseDuel(), incompatibleProfile),
    ).toThrow("RulesProfile incompatível com o Duelo.");
  });

  it.each([1, 3])("rejeita quantidade de jogadores igual a %i", (amount) => {
    const duel = createEndPhaseDuel();
    const players = [
      ...duel.players,
      createMainPhaseOnePlayer("player-3"),
    ].slice(0, amount);
    const invalid = { ...duel, players } as unknown as DuelState;

    expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(
      "O Duelo deve possuir exatamente dois jogadores.",
    );
  });

  it("rejeita jogadores com IDs iguais", () => {
    const duel = createEndPhaseDuel();
    const duplicateIdPlayer: DuelPlayerState = {
      ...duel.players[1],
      playerId: duel.players[0].playerId,
    };
    const invalid: DuelState = {
      ...duel,
      players: [duel.players[0], duplicateIdPlayer],
    };

    expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(
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
      ...createEndPhaseDuel(),
      turnOrder,
    } as unknown as DuelState;

    expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(/turnOrder/);
  });

  it.each(["monsterZones", "spellTrapZones"] as const)(
    "rejeita quantidade incompatível em %s",
    (zoneField) => {
      const duel = createEndPhaseDuel();
      const invalidPlayer: DuelPlayerState = {
        ...duel.players[0],
        [zoneField]: [],
      };
      const invalid: DuelState = {
        ...duel,
        players: [invalidPlayer, duel.players[1]],
      };

      expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(
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
    const duel = createEndPhaseDuel();
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

    expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow(
      "IDs de instância de carta devem ser únicos no Duelo.",
    );
  });

  it("uma falha não modifica a entrada", () => {
    const invalid: DuelState = { ...createEndPhaseDuel(), turnNumber: 0 };
    const snapshot = structuredClone(invalid);

    expect(() => startNextTurn(invalid, gxLegacyProfile)).toThrow();
    expect(invalid).toEqual(snapshot);
  });

  it("usa o fluxo real MAIN_1 → END sem processar a Fase de Compra", () => {
    const mainPhaseOne = createMainPhaseOneDuel();
    const endPhase = transitionFromMainPhaseOne(
      mainPhaseOne,
      gxLegacyProfile,
      "END",
    );
    const result = startNextTurn(endPhase, gxLegacyProfile);

    expect(endPhase.phase).toBe("END");
    expect(result.phase).toBe("DRAW");
    expect(findPlayer(result, "player-2").hand).toEqual(
      findPlayer(endPhase, "player-2").hand,
    );
  });

  it("mantém IDs únicos quando posições de zona são null", () => {
    const duel = replacePlayer(createEndPhaseDuel(), "player-1", (player) => ({
      ...player,
      monsterZones: [null, null, null, null, null],
      spellTrapZones: [null, null, null, null, null],
      fieldZone: null,
    }));

    expect(() => startNextTurn(duel, gxLegacyProfile)).not.toThrow();
  });
});
