import { describe, expect, it, vi } from "vitest";

import {
  createDeterministicRng,
  nextRandomUint32,
  shuffleDeterministically,
  type DeterministicRngState,
} from "../index.js";

const items = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10] as const;

describe("shuffleDeterministically", () => {
  it("retorna um novo array vazio sem consumir chamadas", () => {
    const input: readonly number[] = [];
    const rng = createDeterministicRng("empty-array");
    const result = shuffleDeterministically(input, rng);

    expect(result.items).toEqual([]);
    expect(result.items).not.toBe(input);
    expect(result.nextState).toBe(rng);
    expect(result.nextState.calls).toBe(0);
  });

  it("preserva o único elemento em um novo array sem consumir chamadas", () => {
    const onlyItem = { id: "only" };
    const input = [onlyItem] as const;
    const rng = createDeterministicRng("single-item");
    const result = shuffleDeterministically(input, rng);

    expect(result.items).toEqual([onlyItem]);
    expect(result.items[0]).toBe(onlyItem);
    expect(result.items).not.toBe(input);
    expect(result.nextState).toBe(rng);
    expect(result.nextState.calls).toBe(0);
  });

  it.each([0, 1, 2, 5, 20])(
    "consome exatamente max(0, %i - 1) chamadas",
    (length) => {
      const input = Array.from({ length }, (_, index) => index);
      const initialState = createDeterministicRng(`calls:${length}`);
      const advancedState = nextRandomUint32(initialState).nextState;
      const result = shuffleDeterministically(input, advancedState);

      expect(result.nextState.calls - advancedState.calls).toBe(
        Math.max(0, length - 1),
      );
    },
  );

  it("gera a mesma ordem para a mesma seed e o mesmo array", () => {
    const firstResult = shuffleDeterministically(
      items,
      createDeterministicRng("same-shuffle"),
    );
    const secondResult = shuffleDeterministically(
      items,
      createDeterministicRng("same-shuffle"),
    );

    expect(firstResult).toEqual(secondResult);
  });

  it("gera ordens diferentes para as seeds testadas", () => {
    const seeds = ["shuffle-a", "shuffle-b", "shuffle-c", "shuffle-d"];
    const orders = seeds.map((seed) =>
      JSON.stringify(
        shuffleDeterministically(items, createDeterministicRng(seed)).items,
      ),
    );

    expect(new Set(orders).size).toBe(seeds.length);
  });

  it("não modifica o array recebido", () => {
    const input = Object.freeze([...items]);
    const snapshot = [...input];

    shuffleDeterministically(input, createDeterministicRng("input-array"));

    expect(input).toEqual(snapshot);
  });

  it("não modifica o estado recebido", () => {
    const rng = createDeterministicRng("input-state");
    const snapshot = structuredClone(rng);

    shuffleDeterministically(items, rng);

    expect(rng).toEqual(snapshot);
  });

  it("retorna um array independente da entrada", () => {
    const input = [...items];
    const result = shuffleDeterministically(
      input,
      createDeterministicRng("independent-array"),
    );

    expect(result.items).not.toBe(input);
    expect(Object.isFrozen(result.items)).toBe(true);
  });

  it("preserva todos os elementos sem adicionar ou remover nenhum", () => {
    const input = [10, 20, 30, 40, 50];
    const result = shuffleDeterministically(
      input,
      createDeterministicRng("same-elements"),
    );

    expect(result.items).toHaveLength(input.length);

    for (const item of input) {
      expect(result.items).toContain(item);
    }

    for (const item of result.items) {
      expect(input).toContain(item);
    }
  });

  it("preserva valores duplicados", () => {
    const input = ["a", "b", "a", "c", "a", "b"];
    const result = shuffleDeterministically(
      input,
      createDeterministicRng("duplicates"),
    );
    const count = (values: readonly string[], value: string): number =>
      values.filter((item) => item === value).length;

    expect(result.items).toHaveLength(input.length);
    expect(count(result.items, "a")).toBe(3);
    expect(count(result.items, "b")).toBe(2);
    expect(count(result.items, "c")).toBe(1);
  });

  it("mantém as referências dos objetos", () => {
    const first = { id: 1 };
    const second = { id: 2 };
    const third = { id: 3 };
    const input = [first, second, third];
    const result = shuffleDeterministically(
      input,
      createDeterministicRng("object-references"),
    );

    expect(result.items).toHaveLength(3);
    expect(result.items).toContain(first);
    expect(result.items).toContain(second);
    expect(result.items).toContain(third);
  });

  it("produz o mesmo resultado após serializar e desserializar o estado", () => {
    const advancedState = nextRandomUint32(
      createDeterministicRng("serialized-shuffle"),
    ).nextState;
    const restoredState = JSON.parse(
      JSON.stringify(advancedState),
    ) as DeterministicRngState;

    expect(shuffleDeterministically(items, restoredState)).toEqual(
      shuffleDeterministically(items, advancedState),
    );
  });

  it("continua corretamente a sequência em embaralhamentos consecutivos", () => {
    const initialState = createDeterministicRng("consecutive-shuffles");
    const firstResult = shuffleDeterministically(items, initialState);
    const secondResult = shuffleDeterministically(items, firstResult.nextState);

    const replayFirstResult = shuffleDeterministically(items, initialState);
    const replaySecondResult = shuffleDeterministically(
      items,
      replayFirstResult.nextState,
    );

    expect(secondResult).toEqual(replaySecondResult);
    expect(secondResult.nextState.calls).toBe(2 * (items.length - 1));
  });

  it("não depende de Array.prototype.sort", () => {
    const arraySort = vi
      .spyOn(Array.prototype, "sort")
      .mockImplementation(() => {
        throw new Error("sort não deve ser chamado.");
      });

    try {
      expect(() =>
        shuffleDeterministically(items, createDeterministicRng("without-sort")),
      ).not.toThrow();
      expect(arraySort).not.toHaveBeenCalled();
    } finally {
      arraySort.mockRestore();
    }
  });

  it("permanece independente de Math.random", () => {
    const mathRandom = vi.spyOn(Math, "random").mockImplementation(() => {
      throw new Error("Math.random não deve ser chamado.");
    });

    try {
      expect(() =>
        shuffleDeterministically(
          items,
          createDeterministicRng("without-math-random"),
        ),
      ).not.toThrow();
      expect(mathRandom).not.toHaveBeenCalled();
    } finally {
      mathRandom.mockRestore();
    }
  });
});
