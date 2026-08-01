import { describe, expect, it } from "vitest";

import {
  getRequiredEndPhaseDiscardCount,
  gxLegacyProfile,
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

function createHand(playerId: PlayerId, amount: number): string[] {
  return Array.from(
    { length: amount },
    (_, index) => `${playerId}-query-hand-${index + 1}`,
  );
}

function withHandSize(
  duelState: DuelState,
  playerId: PlayerId,
  amount: number,
): DuelState {
  return replacePlayer(duelState, playerId, (player) => ({
    ...player,
    hand: createHand(playerId, amount),
  }));
}

describe("getRequiredEndPhaseDiscardCount", () => {
  it.each([
    [4, 0],
    [6, 0],
    [7, 1],
    [10, 4],
  ])("com %i cartas na mão retorna %i", (handSize, expected) => {
    const duel = withHandSize(createEndPhaseDuel(), "player-1", handSize);

    expect(getRequiredEndPhaseDiscardCount(duel, gxLegacyProfile)).toBe(
      expected,
    );
  });

  it("funciona quando o jogador atual é players[0]", () => {
    const duel = withHandSize(createEndPhaseDuel(1), "player-1", 8);

    expect(duel.currentPlayerId).toBe(duel.players[0].playerId);
    expect(getRequiredEndPhaseDiscardCount(duel, gxLegacyProfile)).toBe(2);
  });

  it("funciona quando o jogador atual é players[1]", () => {
    const duel = withHandSize(createEndPhaseDuel(2), "player-2", 9);

    expect(duel.currentPlayerId).toBe(duel.players[1].playerId);
    expect(getRequiredEndPhaseDiscardCount(duel, gxLegacyProfile)).toBe(3);
  });

  it("usa somente a mão do jogador atual", () => {
    let duel = withHandSize(createEndPhaseDuel(), "player-1", 7);
    duel = withHandSize(duel, "player-2", 20);

    expect(getRequiredEndPhaseDiscardCount(duel, gxLegacyProfile)).toBe(1);
  });

  it("ignora a quantidade de cartas do outro jogador", () => {
    let smallOtherHand = withHandSize(createEndPhaseDuel(), "player-1", 8);
    smallOtherHand = withHandSize(smallOtherHand, "player-2", 0);
    let largeOtherHand = withHandSize(createEndPhaseDuel(), "player-1", 8);
    largeOtherHand = withHandSize(largeOtherHand, "player-2", 30);

    expect(
      getRequiredEndPhaseDiscardCount(smallOtherHand, gxLegacyProfile),
    ).toBe(2);
    expect(
      getRequiredEndPhaseDiscardCount(largeOtherHand, gxLegacyProfile),
    ).toBe(2);
  });

  it("aceita mão vazia", () => {
    const duel = withHandSize(createEndPhaseDuel(), "player-1", 0);

    expect(getRequiredEndPhaseDiscardCount(duel, gxLegacyProfile)).toBe(0);
  });

  it("não altera a mão", () => {
    const duel = withHandSize(createEndPhaseDuel(), "player-1", 8);
    const hand = duel.players[0].hand;
    const snapshot = [...hand];

    getRequiredEndPhaseDiscardCount(duel, gxLegacyProfile);

    expect(duel.players[0].hand).toBe(hand);
    expect(duel.players[0].hand).toEqual(snapshot);
  });

  it("não altera o Deck", () => {
    const duel = withHandSize(createEndPhaseDuel(), "player-1", 8);
    const mainDeck = duel.players[0].mainDeck;
    const snapshot = [...mainDeck];

    getRequiredEndPhaseDiscardCount(duel, gxLegacyProfile);

    expect(duel.players[0].mainDeck).toBe(mainDeck);
    expect(duel.players[0].mainDeck).toEqual(snapshot);
  });

  it("não altera nenhuma área dos jogadores", () => {
    const duel = withHandSize(createEndPhaseDuel(), "player-1", 8);
    const playerReferences = [...duel.players];
    const arrayReferences = duel.players.map((player) =>
      PLAYER_ARRAY_FIELDS.map((field) => player[field]),
    );
    const snapshot = structuredClone(duel.players);

    getRequiredEndPhaseDiscardCount(duel, gxLegacyProfile);

    expect(duel.players).toEqual(snapshot);
    for (const playerIndex of [0, 1] as const) {
      expect(duel.players[playerIndex]).toBe(playerReferences[playerIndex]);
      for (const [fieldIndex, field] of PLAYER_ARRAY_FIELDS.entries()) {
        expect(duel.players[playerIndex][field]).toBe(
          arrayReferences[playerIndex]?.[fieldIndex],
        );
      }
    }
  });

  it("não altera rngState nem consome chamadas do RNG", () => {
    const duel = withHandSize(createEndPhaseDuel(), "player-1", 8);
    const rngState = duel.rngState;
    const snapshot = structuredClone(rngState);
    const calls = rngState?.calls;

    getRequiredEndPhaseDiscardCount(duel, gxLegacyProfile);

    expect(duel.rngState).toBe(rngState);
    expect(duel.rngState).toEqual(snapshot);
    expect(duel.rngState?.calls).toBe(calls);
  });

  it("não modifica DuelState", () => {
    const duel = withHandSize(createEndPhaseDuel(), "player-1", 8);
    const snapshot = structuredClone(duel);

    getRequiredEndPhaseDiscardCount(duel, gxLegacyProfile);

    expect(duel).toEqual(snapshot);
  });

  it("não modifica RulesProfile", () => {
    const profile: RulesProfile = {
      ...gxLegacyProfile,
      enabledSummons: [...gxLegacyProfile.enabledSummons],
    };
    const snapshot = structuredClone(profile);

    getRequiredEndPhaseDiscardCount(createEndPhaseDuel(), profile);

    expect(profile).toEqual(snapshot);
  });

  it("chamadas repetidas retornam o mesmo valor", () => {
    const duel = withHandSize(createEndPhaseDuel(), "player-1", 10);

    const first = getRequiredEndPhaseDiscardCount(duel, gxLegacyProfile);
    const second = getRequiredEndPhaseDiscardCount(duel, gxLegacyProfile);

    expect(first).toBe(4);
    expect(second).toBe(first);
  });

  it.each(["PREPARING", "FINISHED"] as const)("rejeita status %s", (status) => {
    const invalid: DuelState = { ...createEndPhaseDuel(), status };

    expect(() =>
      getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
    ).toThrow("Somente um Duelo ACTIVE pode consultar o descarte.");
  });

  it.each(["DRAW", "STANDBY", "MAIN_1", "BATTLE", "MAIN_2", null] as const)(
    "rejeita phase diferente de END: %s",
    (phase) => {
      const invalid: DuelState = { ...createEndPhaseDuel(), phase };

      expect(() =>
        getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
      ).toThrow("O Duelo deve estar na fase END.");
    },
  );

  it.each([0, -1, -10, 1.5])("rejeita turnNumber inválido %s", (turnNumber) => {
    const invalid: DuelState = { ...createEndPhaseDuel(), turnNumber };

    expect(() =>
      getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
    ).toThrow("turnNumber deve ser um inteiro maior ou igual a 1.");
  });

  it("rejeita currentPlayerId null", () => {
    const invalid: DuelState = {
      ...createEndPhaseDuel(),
      currentPlayerId: null,
    };

    expect(() =>
      getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
    ).toThrow("A Fase Final deve possuir jogador atual.");
  });

  it("rejeita currentPlayerId inexistente", () => {
    const invalid: DuelState = {
      ...createEndPhaseDuel(),
      currentPlayerId: "unknown-player",
    };

    expect(() =>
      getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
    ).toThrow(
      "currentPlayerId é incompatível com turnOrder e com os jogadores.",
    );
  });

  it("rejeita currentPlayerId incompatível com turnOrder e turnNumber", () => {
    const invalid: DuelState = {
      ...createEndPhaseDuel(2),
      currentPlayerId: "player-1",
    };

    expect(() =>
      getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
    ).toThrow(
      "currentPlayerId é incompatível com turnOrder e com os jogadores.",
    );
  });

  it("rejeita winnerId já definido", () => {
    const invalid: DuelState = {
      ...createEndPhaseDuel(),
      winnerId: "player-2",
    };

    expect(() =>
      getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
    ).toThrow("Um Duelo ACTIVE não pode possuir vencedor.");
  });

  it("rejeita resultReason já definido", () => {
    const invalid: DuelState = {
      ...createEndPhaseDuel(),
      resultReason: "DECK_OUT",
    };

    expect(() =>
      getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
    ).toThrow("Um Duelo ACTIVE não pode possuir motivo de resultado.");
  });

  it("rejeita rngState null", () => {
    const invalid: DuelState = { ...createEndPhaseDuel(), rngState: null };

    expect(() =>
      getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
    ).toThrow("O Duelo deve possuir um estado de RNG.");
  });

  it("rejeita RulesProfile inválido", () => {
    const invalidProfile: RulesProfile = {
      ...gxLegacyProfile,
      startingLifePoints: 0,
    };

    expect(() =>
      getRequiredEndPhaseDiscardCount(createEndPhaseDuel(), invalidProfile),
    ).toThrow("RulesProfile inválido.");
  });

  it("rejeita RulesProfile incompatível com rulesProfileId", () => {
    const incompatibleProfile: RulesProfile = {
      ...gxLegacyProfile,
      id: "OTHER_PROFILE",
    };

    expect(() =>
      getRequiredEndPhaseDiscardCount(
        createEndPhaseDuel(),
        incompatibleProfile,
      ),
    ).toThrow("RulesProfile incompatível com o Duelo.");
  });

  it.each([1, 3])("rejeita quantidade de jogadores igual a %i", (amount) => {
    const duel = createEndPhaseDuel();
    const players = [
      ...duel.players,
      createMainPhaseOnePlayer("player-3"),
    ].slice(0, amount);
    const invalid = { ...duel, players } as unknown as DuelState;

    expect(() =>
      getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
    ).toThrow("O Duelo deve possuir exatamente dois jogadores.");
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

    expect(() =>
      getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
    ).toThrow("Os jogadores devem possuir IDs diferentes.");
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

    expect(() =>
      getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
    ).toThrow(/turnOrder/);
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

      expect(() =>
        getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
      ).toThrow("As zonas do jogador são incompatíveis com o perfil.");
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

    expect(() =>
      getRequiredEndPhaseDiscardCount(invalid, gxLegacyProfile),
    ).toThrow("IDs de instância de carta devem ser únicos no Duelo.");
  });

  it.each([-1, -10])("rejeita handLimit negativo %s", (handLimit) => {
    const invalidProfile: RulesProfile = { ...gxLegacyProfile, handLimit };

    expect(() =>
      getRequiredEndPhaseDiscardCount(createEndPhaseDuel(), invalidProfile),
    ).toThrow("RulesProfile inválido.");
  });

  it.each([6.5, 7.25])("rejeita handLimit decimal %s", (handLimit) => {
    const invalidProfile: RulesProfile = { ...gxLegacyProfile, handLimit };

    expect(() =>
      getRequiredEndPhaseDiscardCount(createEndPhaseDuel(), invalidProfile),
    ).toThrow("RulesProfile inválido.");
  });

  it("uma falha não modifica a entrada", () => {
    const invalid: DuelState = { ...createEndPhaseDuel(), turnNumber: 0 };
    const profile: RulesProfile = {
      ...gxLegacyProfile,
      enabledSummons: [...gxLegacyProfile.enabledSummons],
    };
    const duelSnapshot = structuredClone(invalid);
    const profileSnapshot = structuredClone(profile);

    expect(() => getRequiredEndPhaseDiscardCount(invalid, profile)).toThrow();
    expect(invalid).toEqual(duelSnapshot);
    expect(profile).toEqual(profileSnapshot);
  });

  it("usa o fluxo real MAIN_1 → END sem iniciar o próximo turno", () => {
    const mainPhaseOne = createMainPhaseOneDuel();
    const endPhase = transitionFromMainPhaseOne(
      mainPhaseOne,
      gxLegacyProfile,
      "END",
    );

    const result = getRequiredEndPhaseDiscardCount(
      withHandSize(endPhase, "player-1", 7),
      gxLegacyProfile,
    );

    expect(endPhase.phase).toBe("END");
    expect(endPhase.turnNumber).toBe(1);
    expect(endPhase.currentPlayerId).toBe("player-1");
    expect(result).toBe(1);
  });
});
