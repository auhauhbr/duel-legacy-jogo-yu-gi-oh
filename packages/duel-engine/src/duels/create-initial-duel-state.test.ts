import { describe, expect, it } from "vitest";

import {
  createInitialDuelState,
  createInitialPlayerState,
  gxLegacyProfile,
  type DuelPlayerState,
  type DuelState,
  type RulesProfile,
} from "../index.js";

function createPlayers(): [DuelPlayerState, DuelPlayerState] {
  return [
    createInitialPlayerState(
      gxLegacyProfile,
      "player-1",
      ["p1-main-1", "p1-main-2"],
      ["p1-extra-1"],
    ),
    createInitialPlayerState(
      gxLegacyProfile,
      "player-2",
      ["p2-main-1", "p2-main-2"],
      ["p2-extra-1"],
    ),
  ];
}

function createDuel(
  players: readonly DuelPlayerState[] = createPlayers(),
  firstPlayerId = "player-1",
): DuelState {
  return createInitialDuelState(
    "duel-1",
    gxLegacyProfile,
    "engine-1",
    "pool-1",
    players,
    firstPlayerId,
  );
}

function getPlayerArrayPairs(
  source: DuelPlayerState,
  copy: DuelPlayerState,
): ReadonlyArray<readonly [readonly unknown[], readonly unknown[]]> {
  return [
    [source.mainDeck, copy.mainDeck],
    [source.hand, copy.hand],
    [source.graveyard, copy.graveyard],
    [source.banishedFaceUp, copy.banishedFaceUp],
    [source.banishedFaceDown, copy.banishedFaceDown],
    [source.extraDeckFaceDown, copy.extraDeckFaceDown],
    [source.extraDeckFaceUp, copy.extraDeckFaceUp],
    [source.monsterZones, copy.monsterZones],
    [source.spellTrapZones, copy.spellTrapZones],
  ];
}

describe("createInitialDuelState", () => {
  it("cria um DuelState com exatamente dois jogadores", () => {
    const duel = createDuel();

    expect(duel.players).toHaveLength(2);
    expect(duel.duelId).toBe("duel-1");
    expect(duel.rulesProfileId).toBe("GX_LEGACY");
    expect(duel.engineVersion).toBe("engine-1");
    expect(duel.cardPoolVersion).toBe("pool-1");
  });

  it("começa com status PREPARING", () => {
    expect(createDuel().status).toBe("PREPARING");
  });

  it("começa com turnNumber igual a zero", () => {
    expect(createDuel().turnNumber).toBe(0);
  });

  it("começa sem jogador atual", () => {
    expect(createDuel().currentPlayerId).toBeNull();
  });

  it("começa com phase igual a null", () => {
    expect(createDuel().phase).toBeNull();
  });

  it("começa sem vencedor", () => {
    expect(createDuel().winnerId).toBeNull();
  });

  it("começa sem motivo de resultado", () => {
    expect(createDuel().resultReason).toBeNull();
  });

  it("começa com rngState igual a null", () => {
    expect(createDuel().rngState).toBeNull();
  });

  it("cria turnOrder começando pelo primeiro jogador escolhido", () => {
    expect(createDuel().turnOrder).toEqual(["player-1", "player-2"]);
  });

  it("aceita o segundo jogador como primeiro", () => {
    expect(createDuel(createPlayers(), "player-2").turnOrder).toEqual([
      "player-2",
      "player-1",
    ]);
  });

  it("preserva a ordem e os estados dos jogadores", () => {
    const players = createPlayers();
    const duel = createDuel(players, "player-2");

    expect(duel.players).toEqual(players);
    expect(duel.players.map(({ playerId }) => playerId)).toEqual([
      "player-1",
      "player-2",
    ]);
  });

  it("cria cópias defensivas dos jogadores e de seus arrays", () => {
    const players = createPlayers();
    const duel = createDuel(players);

    expect(duel.players).not.toBe(players);
    expect(duel.players[0]).not.toBe(players[0]);
    expect(duel.players[1]).not.toBe(players[1]);

    for (const [sourceArray, copiedArray] of [
      ...getPlayerArrayPairs(players[0], duel.players[0]),
      ...getPlayerArrayPairs(players[1], duel.players[1]),
    ]) {
      expect(copiedArray).not.toBe(sourceArray);
    }
  });

  it("duas chamadas não compartilham arrays internos", () => {
    const firstDuel = createDuel();
    const secondDuel = createDuel();

    expect(firstDuel.players).not.toBe(secondDuel.players);
    expect(firstDuel.turnOrder).not.toBe(secondDuel.turnOrder);

    for (const playerIndex of [0, 1] as const) {
      expect(firstDuel.players[playerIndex]).not.toBe(
        secondDuel.players[playerIndex],
      );

      for (const [firstArray, secondArray] of getPlayerArrayPairs(
        firstDuel.players[playerIndex],
        secondDuel.players[playerIndex],
      )) {
        expect(firstArray).not.toBe(secondArray);
      }
    }
  });

  it("não modifica nenhum argumento", () => {
    const players = createPlayers();
    const profileSnapshot = structuredClone(gxLegacyProfile);
    const playersSnapshot = structuredClone(players);

    createInitialDuelState(
      "duel-1",
      gxLegacyProfile,
      "engine-1",
      "pool-1",
      players,
      "player-2",
    );

    expect(gxLegacyProfile).toEqual(profileSnapshot);
    expect(players).toEqual(playersSnapshot);
  });

  it.each(["", "   "])("rejeita duelId vazio ou em branco", (duelId) => {
    expect(() =>
      createInitialDuelState(
        duelId,
        gxLegacyProfile,
        "engine-1",
        "pool-1",
        createPlayers(),
        "player-1",
      ),
    ).toThrow("duelId não pode ser vazio.");
  });

  it.each(["", "   "])(
    "rejeita versão do motor vazia ou em branco",
    (engineVersion) => {
      expect(() =>
        createInitialDuelState(
          "duel-1",
          gxLegacyProfile,
          engineVersion,
          "pool-1",
          createPlayers(),
          "player-1",
        ),
      ).toThrow("engineVersion não pode ser vazia.");
    },
  );

  it.each(["", "   "])(
    "rejeita versão do pool vazia ou em branco",
    (cardPoolVersion) => {
      expect(() =>
        createInitialDuelState(
          "duel-1",
          gxLegacyProfile,
          "engine-1",
          cardPoolVersion,
          createPlayers(),
          "player-1",
        ),
      ).toThrow("cardPoolVersion não pode ser vazia.");
    },
  );

  it.each([
    { players: [] },
    { players: [createPlayers()[0]] },
    {
      players: [
        ...createPlayers(),
        createInitialPlayerState(gxLegacyProfile, "player-3", [], []),
      ],
    },
  ])("rejeita quantidade diferente de dois jogadores", ({ players }) => {
    expect(() => createDuel(players)).toThrow(
      "O Duelo deve possuir exatamente dois jogadores.",
    );
  });

  it("rejeita jogadores com o mesmo ID", () => {
    const [firstPlayer, secondPlayer] = createPlayers();
    const duplicatePlayerId: DuelPlayerState = {
      ...secondPlayer,
      playerId: firstPlayer.playerId,
    };

    expect(() => createDuel([firstPlayer, duplicatePlayerId])).toThrow(
      "Os jogadores devem possuir IDs diferentes.",
    );
  });

  it("rejeita jogador inicial que não pertence ao Duelo", () => {
    expect(() => createDuel(createPlayers(), "unknown-player")).toThrow(
      "O jogador inicial deve pertencer ao Duelo.",
    );
  });

  it("rejeita perfil inválido", () => {
    const invalidProfile: RulesProfile = {
      ...gxLegacyProfile,
      startingLifePoints: 0,
    };

    expect(() =>
      createInitialDuelState(
        "duel-1",
        invalidProfile,
        "engine-1",
        "pool-1",
        createPlayers(),
        "player-1",
      ),
    ).toThrow("RulesProfile inválido.");
  });

  it.each(["monsterZones", "spellTrapZones"] as const)(
    "rejeita quantidade incompatível em %s",
    (zoneField) => {
      const [firstPlayer, secondPlayer] = createPlayers();
      const invalidPlayer: DuelPlayerState = {
        ...firstPlayer,
        [zoneField]: [],
      };

      expect(() => createDuel([invalidPlayer, secondPlayer])).toThrow(
        "As zonas do jogador são incompatíveis com o perfil.",
      );
    },
  );

  it("rejeita IDs de cartas duplicados entre jogadores em qualquer área", () => {
    const firstPlayer = createInitialPlayerState(
      gxLegacyProfile,
      "player-1",
      ["shared-card"],
      [],
    );
    const secondPlayer = createInitialPlayerState(
      gxLegacyProfile,
      "player-2",
      [],
      ["shared-card"],
    );

    expect(() => createDuel([firstPlayer, secondPlayer])).toThrow(
      "IDs de instância de carta devem ser únicos no Duelo.",
    );
  });
});
