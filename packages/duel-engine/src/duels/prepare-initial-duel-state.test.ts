import { describe, expect, it } from "vitest";

import {
  createDeterministicRng,
  createInitialDuelState,
  createInitialPlayerState,
  gxLegacyProfile,
  prepareInitialDuelState,
  shuffleDeterministically,
  type DuelPlayerState,
  type DuelState,
  type PlayerId,
  type RulesProfile,
} from "../index.js";

function createPlayer(playerId: PlayerId, deckSize = 10): DuelPlayerState {
  const player = createInitialPlayerState(
    gxLegacyProfile,
    playerId,
    Array.from({ length: deckSize }, (_, index) => `${playerId}-main-${index}`),
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

function createPlayers(deckSize = 10): [DuelPlayerState, DuelPlayerState] {
  return [
    createPlayer("player-1", deckSize),
    createPlayer("player-2", deckSize),
  ];
}

function createDuel(
  firstPlayerId: PlayerId = "player-1",
  players: readonly DuelPlayerState[] = createPlayers(),
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

function getPlayer(duelState: DuelState, playerId: PlayerId): DuelPlayerState {
  const player = duelState.players.find(
    (candidate) => candidate.playerId === playerId,
  );

  if (!player) {
    throw new Error(`Jogador não encontrado no teste: ${playerId}`);
  }

  return player;
}

function getPlayerArrays(
  playerState: DuelPlayerState,
): ReadonlyArray<readonly unknown[]> {
  return [
    playerState.mainDeck,
    playerState.hand,
    playerState.graveyard,
    playerState.banishedFaceUp,
    playerState.banishedFaceDown,
    playerState.extraDeckFaceDown,
    playerState.extraDeckFaceUp,
    playerState.monsterZones,
    playerState.spellTrapZones,
  ];
}

function getExpectedShuffles(duelState: DuelState, seed: string) {
  const firstPlayer = getPlayer(duelState, duelState.turnOrder[0]);
  const secondPlayer = getPlayer(duelState, duelState.turnOrder[1]);
  const initialRng = createDeterministicRng(seed);
  const firstShuffle = shuffleDeterministically(
    firstPlayer.mainDeck,
    initialRng,
  );
  const secondShuffle = shuffleDeterministically(
    secondPlayer.mainDeck,
    firstShuffle.nextState,
  );

  return { firstPlayer, secondPlayer, firstShuffle, secondShuffle };
}

describe("prepareInitialDuelState", () => {
  it("prepara corretamente um Duelo GX_LEGACY", () => {
    const prepared = prepareInitialDuelState(
      createDuel(),
      gxLegacyProfile,
      "prepare-seed",
    );

    expect(prepared.players).toHaveLength(2);
    expect(prepared.players[0].hand).toHaveLength(5);
    expect(prepared.players[1].hand).toHaveLength(5);
    expect(prepared.rngState).not.toBeNull();
  });

  it("continua com status PREPARING e turnNumber zero", () => {
    const prepared = prepareInitialDuelState(
      createDuel(),
      gxLegacyProfile,
      "status-seed",
    );

    expect(prepared.status).toBe("PREPARING");
    expect(prepared.turnNumber).toBe(0);
  });

  it("continua sem jogador atual, vencedor ou motivo de resultado", () => {
    const prepared = prepareInitialDuelState(
      createDuel(),
      gxLegacyProfile,
      "result-seed",
    );

    expect(prepared.currentPlayerId).toBeNull();
    expect(prepared.winnerId).toBeNull();
    expect(prepared.resultReason).toBeNull();
  });

  it("continua com phase igual a null", () => {
    const prepared = prepareInitialDuelState(
      createDuel(),
      gxLegacyProfile,
      "phase-seed",
    );

    expect(prepared.phase).toBeNull();
  });

  it("armazena o estado final do RNG", () => {
    const duel = createDuel();
    const expected = getExpectedShuffles(duel, "rng-state-seed");
    const prepared = prepareInitialDuelState(
      duel,
      gxLegacyProfile,
      "rng-state-seed",
    );

    expect(prepared.rngState).toEqual(expected.secondShuffle.nextState);
  });

  it("distribui exatamente cinco cartas para cada jogador", () => {
    const prepared = prepareInitialDuelState(
      createDuel(),
      gxLegacyProfile,
      "hand-size-seed",
    );

    for (const player of prepared.players) {
      expect(player.hand).toHaveLength(5);
      expect(player.mainDeck).toHaveLength(5);
    }
  });

  it("retira da mão as cartas do topo de cada Deck embaralhado", () => {
    const duel = createDuel();
    const expected = getExpectedShuffles(duel, "top-cards-seed");
    const prepared = prepareInitialDuelState(
      duel,
      gxLegacyProfile,
      "top-cards-seed",
    );
    const preparedFirst = getPlayer(prepared, duel.turnOrder[0]);
    const preparedSecond = getPlayer(prepared, duel.turnOrder[1]);

    expect(preparedFirst.hand).toEqual(expected.firstShuffle.items.slice(0, 5));
    expect(preparedFirst.mainDeck).toEqual(
      expected.firstShuffle.items.slice(5),
    );
    expect(preparedSecond.hand).toEqual(
      expected.secondShuffle.items.slice(0, 5),
    );
    expect(preparedSecond.mainDeck).toEqual(
      expected.secondShuffle.items.slice(5),
    );
  });

  it("produz o mesmo resultado com a mesma seed e o mesmo estado", () => {
    const duel = createDuel();

    expect(
      prepareInitialDuelState(duel, gxLegacyProfile, "same-prepare-seed"),
    ).toEqual(
      prepareInitialDuelState(duel, gxLegacyProfile, "same-prepare-seed"),
    );
  });

  it("produz resultados diferentes para as seeds testadas", () => {
    const duel = createDuel();
    const seeds = ["prepare-a", "prepare-b", "prepare-c", "prepare-d"];
    const results = seeds.map((seed) =>
      JSON.stringify(prepareInitialDuelState(duel, gxLegacyProfile, seed)),
    );

    expect(new Set(results).size).toBe(seeds.length);
  });

  it("mudar turnOrder muda a ordem de consumo do RNG", () => {
    const firstOrder = prepareInitialDuelState(
      createDuel("player-1"),
      gxLegacyProfile,
      "turn-order-seed",
    );
    const secondOrder = prepareInitialDuelState(
      createDuel("player-2"),
      gxLegacyProfile,
      "turn-order-seed",
    );

    expect(getPlayer(firstOrder, "player-1").hand).not.toEqual(
      getPlayer(secondOrder, "player-1").hand,
    );
  });

  it("embaralha primeiro turnOrder[0]", () => {
    const duel = createDuel("player-2");
    const expected = getExpectedShuffles(duel, "first-shuffle-seed");
    const prepared = prepareInitialDuelState(
      duel,
      gxLegacyProfile,
      "first-shuffle-seed",
    );
    const firstPrepared = getPlayer(prepared, duel.turnOrder[0]);

    expect([...firstPrepared.hand, ...firstPrepared.mainDeck]).toEqual(
      expected.firstShuffle.items,
    );
  });

  it("embaralha turnOrder[1] usando o estado posterior ao primeiro shuffle", () => {
    const duel = createDuel("player-2");
    const expected = getExpectedShuffles(duel, "second-shuffle-seed");
    const prepared = prepareInitialDuelState(
      duel,
      gxLegacyProfile,
      "second-shuffle-seed",
    );
    const secondPrepared = getPlayer(prepared, duel.turnOrder[1]);

    expect([...secondPrepared.hand, ...secondPrepared.mainDeck]).toEqual(
      expected.secondShuffle.items,
    );
  });

  it("a compra não aumenta rngState.calls", () => {
    const duel = createDuel();
    const expected = getExpectedShuffles(duel, "draw-calls-seed");
    const prepared = prepareInitialDuelState(
      duel,
      gxLegacyProfile,
      "draw-calls-seed",
    );

    expect(prepared.rngState?.calls).toBe(
      expected.secondShuffle.nextState.calls,
    );
  });

  it("contabiliza apenas as chamadas dos dois embaralhamentos", () => {
    const deckSize = 10;
    const prepared = prepareInitialDuelState(
      createDuel("player-1", createPlayers(deckSize)),
      gxLegacyProfile,
      "total-calls-seed",
    );

    expect(prepared.rngState?.calls).toBe(2 * (deckSize - 1));
  });

  it("preserva turnOrder, identificadores e versões", () => {
    const duel = createDuel("player-2");
    const prepared = prepareInitialDuelState(
      duel,
      gxLegacyProfile,
      "identity-seed",
    );

    expect(prepared.turnOrder).toEqual(duel.turnOrder);
    expect(prepared.duelId).toBe(duel.duelId);
    expect(prepared.rulesProfileId).toBe(duel.rulesProfileId);
    expect(prepared.engineVersion).toBe(duel.engineVersion);
    expect(prepared.cardPoolVersion).toBe(duel.cardPoolVersion);
  });

  it("preserva áreas não relacionadas dos jogadores", () => {
    const duel = createDuel();
    const prepared = prepareInitialDuelState(
      duel,
      gxLegacyProfile,
      "unrelated-areas-seed",
    );

    for (const inputPlayer of duel.players) {
      const outputPlayer = getPlayer(prepared, inputPlayer.playerId);

      expect(outputPlayer).toMatchObject({
        playerId: inputPlayer.playerId,
        lifePoints: inputPlayer.lifePoints,
        graveyard: inputPlayer.graveyard,
        banishedFaceUp: inputPlayer.banishedFaceUp,
        banishedFaceDown: inputPlayer.banishedFaceDown,
        extraDeckFaceDown: inputPlayer.extraDeckFaceDown,
        extraDeckFaceUp: inputPlayer.extraDeckFaceUp,
        monsterZones: inputPlayer.monsterZones,
        spellTrapZones: inputPlayer.spellTrapZones,
        fieldZone: inputPlayer.fieldZone,
        normalSummonsUsed: inputPlayer.normalSummonsUsed,
        normalSummonLimit: inputPlayer.normalSummonLimit,
      });
    }
  });

  it("não modifica DuelState, RulesProfile ou jogadores recebidos", () => {
    const duel = createDuel();
    const duelSnapshot = structuredClone(duel);
    const profileSnapshot = structuredClone(gxLegacyProfile);
    const playerReferences = [...duel.players];

    prepareInitialDuelState(duel, gxLegacyProfile, "immutable-seed");

    expect(duel).toEqual(duelSnapshot);
    expect(gxLegacyProfile).toEqual(profileSnapshot);
    expect(duel.players[0]).toBe(playerReferences[0]);
    expect(duel.players[1]).toBe(playerReferences[1]);
  });

  it("não compartilha arrays internos entre entrada e saída", () => {
    const duel = createDuel();
    const prepared = prepareInitialDuelState(
      duel,
      gxLegacyProfile,
      "no-shared-arrays-seed",
    );

    expect(prepared.players).not.toBe(duel.players);
    expect(prepared.turnOrder).not.toBe(duel.turnOrder);

    for (const inputPlayer of duel.players) {
      const outputPlayer = getPlayer(prepared, inputPlayer.playerId);
      const inputArrays = getPlayerArrays(inputPlayer);
      const outputArrays = getPlayerArrays(outputPlayer);

      for (const [index, inputArray] of inputArrays.entries()) {
        expect(outputArrays[index]).not.toBe(inputArray);
      }
    }
  });

  it("duas preparações independentes não compartilham arrays", () => {
    const duel = createDuel();
    const first = prepareInitialDuelState(duel, gxLegacyProfile, "shared-seed");
    const second = prepareInitialDuelState(
      duel,
      gxLegacyProfile,
      "shared-seed",
    );

    expect(first.players).not.toBe(second.players);
    expect(first.turnOrder).not.toBe(second.turnOrder);

    for (const playerId of duel.turnOrder) {
      const firstArrays = getPlayerArrays(getPlayer(first, playerId));
      const secondArrays = getPlayerArrays(getPlayer(second, playerId));

      for (const [index, firstArray] of firstArrays.entries()) {
        expect(secondArrays[index]).not.toBe(firstArray);
      }
    }
  });

  it("retorna estruturas congeladas", () => {
    const prepared = prepareInitialDuelState(
      createDuel(),
      gxLegacyProfile,
      "frozen-seed",
    );

    expect(Object.isFrozen(prepared)).toBe(true);
    expect(Object.isFrozen(prepared.players)).toBe(true);
    expect(Object.isFrozen(prepared.turnOrder)).toBe(true);
    expect(Object.isFrozen(prepared.rngState)).toBe(true);

    for (const player of prepared.players) {
      expect(Object.isFrozen(player)).toBe(true);

      for (const array of getPlayerArrays(player)) {
        expect(Object.isFrozen(array)).toBe(true);
      }
    }
  });

  it("rejeita status diferente de PREPARING", () => {
    const invalidDuel: DuelState = { ...createDuel(), status: "ACTIVE" };

    expect(() =>
      prepareInitialDuelState(invalidDuel, gxLegacyProfile, "status-error"),
    ).toThrow("Somente um Duelo em PREPARING pode ser preparado.");
  });

  it("rejeita Duelo que já possui rngState", () => {
    const prepared = prepareInitialDuelState(
      createDuel(),
      gxLegacyProfile,
      "already-prepared",
    );

    expect(() =>
      prepareInitialDuelState(prepared, gxLegacyProfile, "second-prepare"),
    ).toThrow("O Duelo já possui um estado de RNG.");
  });

  it("rejeita perfil incompatível", () => {
    const incompatibleProfile: RulesProfile = {
      ...gxLegacyProfile,
      id: "OTHER_PROFILE",
    };

    expect(() =>
      prepareInitialDuelState(
        createDuel(),
        incompatibleProfile,
        "profile-mismatch",
      ),
    ).toThrow("RulesProfile incompatível com o Duelo.");
  });

  it("rejeita perfil inválido", () => {
    const invalidProfile: RulesProfile = {
      ...gxLegacyProfile,
      startingLifePoints: 0,
    };

    expect(() =>
      prepareInitialDuelState(createDuel(), invalidProfile, "invalid-profile"),
    ).toThrow("RulesProfile inválido.");
  });

  it.each(["", "   "])("rejeita seed vazia ou em branco", (seed) => {
    expect(() =>
      prepareInitialDuelState(createDuel(), gxLegacyProfile, seed),
    ).toThrow("A seed não pode ser vazia.");
  });

  it.each([1, 3])("rejeita quantidade de jogadores igual a %i", (amount) => {
    const players = [
      createPlayer("player-1"),
      createPlayer("player-2"),
      createPlayer("player-3"),
    ].slice(0, amount);
    const duel = {
      ...createDuel(),
      players,
    } as unknown as DuelState;

    expect(() =>
      prepareInitialDuelState(duel, gxLegacyProfile, "player-count-error"),
    ).toThrow("O Duelo deve possuir exatamente dois jogadores.");
  });

  it("rejeita jogador sem cartas suficientes para a mão inicial", () => {
    const players = createPlayers();
    const insufficientPlayer: DuelPlayerState = {
      ...players[0],
      mainDeck: players[0].mainDeck.slice(0, 4),
    };
    const duel: DuelState = {
      ...createDuel(),
      players: [insufficientPlayer, players[1]],
    };

    expect(() =>
      prepareInitialDuelState(duel, gxLegacyProfile, "insufficient-deck"),
    ).toThrow("Jogador sem cartas suficientes para a mão inicial.");
  });

  it("rejeita turnOrder incompatível", () => {
    const duel: DuelState = {
      ...createDuel(),
      turnOrder: ["player-1", "unknown-player"],
    };

    expect(() =>
      prepareInitialDuelState(duel, gxLegacyProfile, "turn-order-error"),
    ).toThrow("turnOrder é incompatível com os jogadores do Duelo.");
  });

  it.each(["monsterZones", "spellTrapZones"] as const)(
    "rejeita quantidade incompatível em %s",
    (zoneField) => {
      const players = createPlayers();
      const invalidPlayer: DuelPlayerState = {
        ...players[0],
        [zoneField]: [],
      };
      const duel: DuelState = {
        ...createDuel(),
        players: [invalidPlayer, players[1]],
      };

      expect(() =>
        prepareInitialDuelState(duel, gxLegacyProfile, "zone-error"),
      ).toThrow("As zonas do jogador são incompatíveis com o perfil.");
    },
  );

  it("rejeita IDs de instância duplicados globalmente", () => {
    const players = createPlayers();
    const duplicatePlayer: DuelPlayerState = {
      ...players[1],
      mainDeck: [players[0].mainDeck[0]!, ...players[1].mainDeck.slice(1)],
    };
    const duel: DuelState = {
      ...createDuel(),
      players: [players[0], duplicatePlayer],
    };

    expect(() =>
      prepareInitialDuelState(duel, gxLegacyProfile, "duplicate-error"),
    ).toThrow("IDs de instância de carta devem ser únicos no Duelo.");
  });

  it("erro durante preparação não modifica a entrada", () => {
    const duel: DuelState = {
      ...createDuel(),
      turnOrder: ["player-1", "unknown-player"],
    };
    const snapshot = structuredClone(duel);

    expect(() =>
      prepareInitialDuelState(duel, gxLegacyProfile, "immutable-error"),
    ).toThrow();
    expect(duel).toEqual(snapshot);
  });
});
