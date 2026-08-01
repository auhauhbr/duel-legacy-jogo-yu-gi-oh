import { describe, expect, it } from "vitest";

import {
  createInitialPlayerState,
  drawCardsFromMainDeck,
  gxLegacyProfile,
  type DuelPlayerState,
} from "../index.js";

function createPlayerState(): DuelPlayerState {
  const initialState = createInitialPlayerState(
    gxLegacyProfile,
    "player-1",
    ["A", "B", "C", "D"],
    ["extra-down"],
  );

  return {
    ...initialState,
    lifePoints: 7_500,
    hand: ["X"],
    graveyard: ["graveyard-card"],
    banishedFaceUp: ["banished-up-card"],
    banishedFaceDown: ["banished-down-card"],
    extraDeckFaceUp: ["extra-up"],
    monsterZones: ["monster-card", null, null, null, null],
    spellTrapZones: [null, "spell-card", null, null, null],
    fieldZone: "field-card",
    normalSummonsUsed: 1,
    normalSummonLimit: 2,
  };
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

describe("drawCardsFromMainDeck", () => {
  it("compra uma carta do topo", () => {
    const result = drawCardsFromMainDeck(createPlayerState(), 1);

    expect(result.drawnCardIds).toEqual(["A"]);
    expect(result.playerState.mainDeck).toEqual(["B", "C", "D"]);
  });

  it("compra várias cartas preservando sua ordem", () => {
    const result = drawCardsFromMainDeck(createPlayerState(), 3);

    expect(result.drawnCardIds).toEqual(["A", "B", "C"]);
    expect(result.playerState.mainDeck).toEqual(["D"]);
  });

  it("trata o índice zero como o topo do Deck", () => {
    const result = drawCardsFromMainDeck(createPlayerState(), 2);

    expect(result.drawnCardIds[0]).toBe("A");
    expect(result.drawnCardIds[1]).toBe("B");
  });

  it("adiciona as cartas compradas ao final da mão existente", () => {
    const result = drawCardsFromMainDeck(createPlayerState(), 2);

    expect(result.playerState.hand).toEqual(["X", "A", "B"]);
  });

  it("remove exatamente as cartas compradas do Deck Principal", () => {
    const result = drawCardsFromMainDeck(createPlayerState(), 2);

    expect(result.playerState.mainDeck).toEqual(["C", "D"]);
    expect(result.playerState.mainDeck).toHaveLength(2);
  });

  it("preserva o conteúdo de todas as demais áreas", () => {
    const state = createPlayerState();
    const result = drawCardsFromMainDeck(state, 2).playerState;

    expect(result).toMatchObject({
      playerId: state.playerId,
      lifePoints: state.lifePoints,
      graveyard: state.graveyard,
      banishedFaceUp: state.banishedFaceUp,
      banishedFaceDown: state.banishedFaceDown,
      extraDeckFaceDown: state.extraDeckFaceDown,
      extraDeckFaceUp: state.extraDeckFaceUp,
      monsterZones: state.monsterZones,
      spellTrapZones: state.spellTrapZones,
      fieldZone: state.fieldZone,
      normalSummonsUsed: state.normalSummonsUsed,
      normalSummonLimit: state.normalSummonLimit,
    });
  });

  it("aceita compra de zero cartas sem alterar o conteúdo", () => {
    const state = createPlayerState();
    const result = drawCardsFromMainDeck(state, 0);

    expect(result.drawnCardIds).toEqual([]);
    expect(result.playerState).toEqual(state);
  });

  it("compra zero criando estruturas independentes", () => {
    const state = createPlayerState();
    const result = drawCardsFromMainDeck(state, 0);

    expect(result.playerState).not.toBe(state);
    expect(result.drawnCardIds).not.toBe(state.mainDeck);

    const inputArrays = getPlayerArrays(state);
    const outputArrays = getPlayerArrays(result.playerState);

    for (const [index, inputArray] of inputArrays.entries()) {
      expect(outputArrays[index]).not.toBe(inputArray);
    }
  });

  it("compra todo o Deck e deixa o Deck Principal vazio", () => {
    const result = drawCardsFromMainDeck(createPlayerState(), 4);

    expect(result.drawnCardIds).toEqual(["A", "B", "C", "D"]);
    expect(result.playerState.mainDeck).toEqual([]);
    expect(result.playerState.hand).toEqual(["X", "A", "B", "C", "D"]);
  });

  it("rejeita quantidade negativa", () => {
    expect(() => drawCardsFromMainDeck(createPlayerState(), -1)).toThrow(
      "A quantidade de compra não pode ser negativa.",
    );
  });

  it("rejeita quantidade decimal", () => {
    expect(() => drawCardsFromMainDeck(createPlayerState(), 1.5)).toThrow(
      "A quantidade de compra deve ser um inteiro finito.",
    );
  });

  it("rejeita NaN", () => {
    expect(() =>
      drawCardsFromMainDeck(createPlayerState(), Number.NaN),
    ).toThrow("A quantidade de compra deve ser um inteiro finito.");
  });

  it.each([Number.POSITIVE_INFINITY, Number.NEGATIVE_INFINITY])(
    "rejeita quantidade infinita",
    (amount) => {
      expect(() => drawCardsFromMainDeck(createPlayerState(), amount)).toThrow(
        "A quantidade de compra deve ser um inteiro finito.",
      );
    },
  );

  it("rejeita quantidade superior às cartas disponíveis", () => {
    expect(() => drawCardsFromMainDeck(createPlayerState(), 5)).toThrow(
      "O Deck Principal não possui cartas suficientes.",
    );
  });

  it("não modifica o estado nem seus arrays internos", () => {
    const state = createPlayerState();
    const stateSnapshot = structuredClone(state);
    const arrayReferences = getPlayerArrays(state);

    drawCardsFromMainDeck(state, 2);

    expect(state).toEqual(stateSnapshot);
    expect(getPlayerArrays(state)).toEqual(arrayReferences);

    for (const [index, arrayReference] of arrayReferences.entries()) {
      expect(getPlayerArrays(state)[index]).toBe(arrayReference);
    }
  });

  it("não compartilha arrays com o estado de entrada", () => {
    const state = createPlayerState();
    const result = drawCardsFromMainDeck(state, 2);
    const inputArrays = getPlayerArrays(state);
    const outputArrays = getPlayerArrays(result.playerState);

    for (const [index, inputArray] of inputArrays.entries()) {
      expect(outputArrays[index]).not.toBe(inputArray);
    }
  });

  it("drawnCardIds não compartilha referência com Deck ou mão", () => {
    const result = drawCardsFromMainDeck(createPlayerState(), 2);

    expect(result.drawnCardIds).not.toBe(result.playerState.mainDeck);
    expect(result.drawnCardIds).not.toBe(result.playerState.hand);
  });

  it("duas chamadas independentes não compartilham arrays", () => {
    const state = createPlayerState();
    const firstResult = drawCardsFromMainDeck(state, 2);
    const secondResult = drawCardsFromMainDeck(state, 2);

    expect(firstResult.playerState).not.toBe(secondResult.playerState);
    expect(firstResult.drawnCardIds).not.toBe(secondResult.drawnCardIds);

    const firstArrays = getPlayerArrays(firstResult.playerState);
    const secondArrays = getPlayerArrays(secondResult.playerState);

    for (const [index, firstArray] of firstArrays.entries()) {
      expect(secondArrays[index]).not.toBe(firstArray);
    }
  });

  it("congela os objetos e arrays retornados", () => {
    const result = drawCardsFromMainDeck(createPlayerState(), 2);

    expect(Object.isFrozen(result)).toBe(true);
    expect(Object.isFrozen(result.playerState)).toBe(true);
    expect(Object.isFrozen(result.drawnCardIds)).toBe(true);

    for (const array of getPlayerArrays(result.playerState)) {
      expect(Object.isFrozen(array)).toBe(true);
    }
  });
});
