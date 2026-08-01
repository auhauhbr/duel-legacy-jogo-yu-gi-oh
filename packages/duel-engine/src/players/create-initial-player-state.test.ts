import { describe, expect, it } from "vitest";

import {
  createInitialPlayerState,
  gxLegacyProfile,
  type CardInstanceId,
  type DuelPlayerState,
  type RulesProfile,
} from "../index.js";

const mainDeck: readonly CardInstanceId[] = ["main-1", "main-2", "main-3"];
const extraDeck: readonly CardInstanceId[] = ["extra-1", "extra-2"];

function createState(): DuelPlayerState {
  return createInitialPlayerState(
    gxLegacyProfile,
    "player-1",
    mainDeck,
    extraDeck,
  );
}

describe("createInitialPlayerState", () => {
  it("cria o estado inicial usando GX_LEGACY", () => {
    const state = createState();

    expect(state.playerId).toBe("player-1");
    expect(state.lifePoints).toBe(8000);
    expect(state.normalSummonsUsed).toBe(0);
    expect(state.normalSummonLimit).toBe(1);
  });

  it("cria exatamente cinco Zonas de Monstro vazias", () => {
    const state = createState();

    expect(state.monsterZones).toHaveLength(5);
    expect(state.monsterZones).toEqual([null, null, null, null, null]);
  });

  it("cria exatamente cinco Zonas de Magia e Armadilha vazias", () => {
    const state = createState();

    expect(state.spellTrapZones).toHaveLength(5);
    expect(state.spellTrapZones).toEqual([null, null, null, null, null]);
  });

  it("inicia a Zona do Campo vazia", () => {
    expect(createState().fieldZone).toBeNull();
  });

  it("preserva a ordem recebida do Deck Principal", () => {
    expect(createState().mainDeck).toEqual(["main-1", "main-2", "main-3"]);
  });

  it("coloca o Deck Adicional recebido com a face para baixo", () => {
    const state = createState();

    expect(state.extraDeckFaceDown).toEqual(["extra-1", "extra-2"]);
    expect(state.extraDeckFaceUp).toEqual([]);
  });

  it("copia os arrays de Deck recebidos", () => {
    const mutableMainDeck = ["main-1", "main-2"];
    const mutableExtraDeck = ["extra-1"];
    const state = createInitialPlayerState(
      gxLegacyProfile,
      "player-1",
      mutableMainDeck,
      mutableExtraDeck,
    );

    expect(state.mainDeck).not.toBe(mutableMainDeck);
    expect(state.extraDeckFaceDown).not.toBe(mutableExtraDeck);

    mutableMainDeck.push("main-3");
    mutableExtraDeck.push("extra-2");

    expect(state.mainDeck).toEqual(["main-1", "main-2"]);
    expect(state.extraDeckFaceDown).toEqual(["extra-1"]);
  });

  it("não modifica o perfil nem os arrays recebidos", () => {
    const receivedMainDeck = ["main-1", "main-2"];
    const receivedExtraDeck = ["extra-1"];
    const profileSnapshot = structuredClone(gxLegacyProfile);
    const mainDeckSnapshot = [...receivedMainDeck];
    const extraDeckSnapshot = [...receivedExtraDeck];

    createInitialPlayerState(
      gxLegacyProfile,
      "player-1",
      receivedMainDeck,
      receivedExtraDeck,
    );

    expect(gxLegacyProfile).toEqual(profileSnapshot);
    expect(receivedMainDeck).toEqual(mainDeckSnapshot);
    expect(receivedExtraDeck).toEqual(extraDeckSnapshot);
  });

  it("inicia mão, Cemitério e zonas banidas vazios", () => {
    const state = createState();

    expect(state.hand).toEqual([]);
    expect(state.graveyard).toEqual([]);
    expect(state.banishedFaceUp).toEqual([]);
    expect(state.banishedFaceDown).toEqual([]);
  });

  it.each(["", "   "])("rejeita playerId vazio ou em branco", (playerId) => {
    expect(() =>
      createInitialPlayerState(gxLegacyProfile, playerId, mainDeck, extraDeck),
    ).toThrow("playerId não pode ser vazio.");
  });

  it.each([
    { mainDeck: [""], extraDeck: [] },
    { mainDeck: ["   "], extraDeck: [] },
    { mainDeck: [], extraDeck: [""] },
    { mainDeck: [], extraDeck: ["   "] },
  ])("rejeita ID de carta vazio", ({ mainDeck, extraDeck }) => {
    expect(() =>
      createInitialPlayerState(
        gxLegacyProfile,
        "player-1",
        mainDeck,
        extraDeck,
      ),
    ).toThrow("IDs de instância de carta não podem ser vazios.");
  });

  it.each([
    { mainDeck: ["duplicate", "duplicate"], extraDeck: [] },
    { mainDeck: [], extraDeck: ["duplicate", "duplicate"] },
    { mainDeck: ["duplicate"], extraDeck: ["duplicate"] },
  ])("rejeita IDs duplicados", ({ mainDeck, extraDeck }) => {
    expect(() =>
      createInitialPlayerState(
        gxLegacyProfile,
        "player-1",
        mainDeck,
        extraDeck,
      ),
    ).toThrow("IDs de instância de carta devem ser únicos.");
  });

  it("rejeita Deck Adicional acima do limite do perfil", () => {
    const oversizedExtraDeck = Array.from(
      { length: gxLegacyProfile.extraDeckMax + 1 },
      (_, index) => `extra-${index}`,
    );

    expect(() =>
      createInitialPlayerState(
        gxLegacyProfile,
        "player-1",
        [],
        oversizedExtraDeck,
      ),
    ).toThrow("Deck Adicional excede o limite do perfil.");
  });

  it("rejeita perfil inválido", () => {
    const invalidProfile: RulesProfile = {
      ...gxLegacyProfile,
      startingLifePoints: 0,
    };

    expect(() =>
      createInitialPlayerState(invalidProfile, "player-1", mainDeck, extraDeck),
    ).toThrow("RulesProfile inválido.");
  });

  it("não compartilha arrays internos entre duas chamadas", () => {
    const firstState = createState();
    const secondState = createState();

    const arrayPairs: ReadonlyArray<
      readonly [readonly unknown[], readonly unknown[]]
    > = [
      [firstState.mainDeck, secondState.mainDeck],
      [firstState.hand, secondState.hand],
      [firstState.graveyard, secondState.graveyard],
      [firstState.banishedFaceUp, secondState.banishedFaceUp],
      [firstState.banishedFaceDown, secondState.banishedFaceDown],
      [firstState.extraDeckFaceDown, secondState.extraDeckFaceDown],
      [firstState.extraDeckFaceUp, secondState.extraDeckFaceUp],
      [firstState.monsterZones, secondState.monsterZones],
      [firstState.spellTrapZones, secondState.spellTrapZones],
    ];

    for (const [firstArray, secondArray] of arrayPairs) {
      expect(firstArray).not.toBe(secondArray);
    }
  });
});
