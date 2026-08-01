import { describe, expect, it, vi } from "vitest";

import {
  createDeterministicRng,
  nextRandomFloat,
  nextRandomInt,
  nextRandomUint32,
  type DeterministicRngState,
} from "../index.js";

const UINT32_MAX = 0xffff_ffff;
const UINT32_RANGE = 0x1_0000_0000;

function getUint32Sequence(seed: string, length: number): number[] {
  const values: number[] = [];
  let rng = createDeterministicRng(seed);

  for (let index = 0; index < length; index += 1) {
    const result = nextRandomUint32(rng);
    values.push(result.value);
    rng = result.nextState;
  }

  return values;
}

describe("createDeterministicRng", () => {
  it("cria o mesmo estado inicial para a mesma seed", () => {
    expect(createDeterministicRng("duel-seed")).toEqual(
      createDeterministicRng("duel-seed"),
    );
  });

  it("produz estados iniciais diferentes para as seeds testadas", () => {
    const seeds = ["alpha", "beta", "gamma", "delta", "GX 2026", "🔥"];
    const states = seeds.map((seed) => createDeterministicRng(seed).state);

    expect(new Set(states).size).toBe(seeds.length);
  });

  it.each(["", " ", "   ", "\t\n"])(
    "rejeita seed vazia ou composta apenas por espaços",
    (seed) => {
      expect(() => createDeterministicRng(seed)).toThrow(
        "A seed não pode ser vazia.",
      );
    },
  );

  it("preserva caracteres e espaços válidos da seed", () => {
    const seed = "  Duel GX / rodada 01 🔥  ";

    expect(createDeterministicRng(seed).seed).toBe(seed);
  });

  it("começa com calls igual a zero e estado uint32 não nulo", () => {
    const rng = createDeterministicRng("initial-state");

    expect(rng.calls).toBe(0);
    expect(Number.isInteger(rng.state)).toBe(true);
    expect(rng.state).toBeGreaterThan(0);
    expect(rng.state).toBeLessThanOrEqual(UINT32_MAX);
  });
});

describe("nextRandomUint32", () => {
  it("produz a mesma sequência para a mesma seed", () => {
    expect(getUint32Sequence("replay-seed", 20)).toEqual(
      getUint32Sequence("replay-seed", 20),
    );
  });

  it("produz sequências diferentes para seeds diferentes", () => {
    expect(getUint32Sequence("seed-a", 10)).not.toEqual(
      getUint32Sequence("seed-b", 10),
    );
  });

  it("sempre permanece no intervalo uint32", () => {
    const sequence = getUint32Sequence("uint32-range", 500);

    for (const value of sequence) {
      expect(Number.isInteger(value)).toBe(true);
      expect(value).toBeGreaterThanOrEqual(0);
      expect(value).toBeLessThanOrEqual(UINT32_MAX);
    }
  });

  it("incrementa calls exatamente uma vez", () => {
    const rng = createDeterministicRng("calls-uint32");
    const result = nextRandomUint32(rng);

    expect(result.nextState.calls).toBe(rng.calls + 1);
  });
});

describe("nextRandomFloat", () => {
  it("sempre permanece no intervalo [0, 1)", () => {
    let rng = createDeterministicRng("float-range");

    for (let index = 0; index < 500; index += 1) {
      const result = nextRandomFloat(rng);

      expect(result.value).toBeGreaterThanOrEqual(0);
      expect(result.value).toBeLessThan(1);
      rng = result.nextState;
    }
  });

  it("incrementa calls exatamente uma vez", () => {
    const rng = createDeterministicRng("calls-float");
    const result = nextRandomFloat(rng);

    expect(result.nextState.calls).toBe(rng.calls + 1);
  });
});

describe("nextRandomInt", () => {
  it.each([
    [-10, -2],
    [0, 2],
    [10, 1_000],
    [-2_000_000_000, 2_000_000_000],
    [0, UINT32_RANGE],
  ])("respeita o intervalo [%i, %i)", (minInclusive, maxExclusive) => {
    let rng = createDeterministicRng(
      `int-range:${minInclusive}:${maxExclusive}`,
    );

    for (let index = 0; index < 100; index += 1) {
      const result = nextRandomInt(rng, minInclusive, maxExclusive);

      expect(Number.isInteger(result.value)).toBe(true);
      expect(result.value).toBeGreaterThanOrEqual(minInclusive);
      expect(result.value).toBeLessThan(maxExclusive);
      rng = result.nextState;
    }
  });

  it("sempre retorna o único valor de um intervalo unitário", () => {
    let rng = createDeterministicRng("single-value");

    for (let index = 0; index < 50; index += 1) {
      const result = nextRandomInt(rng, 7, 8);

      expect(result.value).toBe(7);
      rng = result.nextState;
    }
  });

  it.each([
    [0.5, 2],
    [0, 2.5],
    [Number.NaN, 2],
    [0, Number.POSITIVE_INFINITY],
  ])("rejeita limites não inteiros", (minInclusive, maxExclusive) => {
    const rng = createDeterministicRng("invalid-decimal");

    expect(() => nextRandomInt(rng, minInclusive, maxExclusive)).toThrow(
      "Os limites devem ser inteiros seguros.",
    );
  });

  it.each([
    [0, 0],
    [10, 5],
    [-1, -1],
  ])("rejeita intervalo vazio ou invertido", (minInclusive, maxExclusive) => {
    const rng = createDeterministicRng("invalid-order");

    expect(() => nextRandomInt(rng, minInclusive, maxExclusive)).toThrow(
      "minInclusive deve ser menor que maxExclusive.",
    );
  });

  it("rejeita intervalo maior que o espaço uint32", () => {
    const rng = createDeterministicRng("oversized-range");

    expect(() => nextRandomInt(rng, 0, UINT32_RANGE + 1)).toThrow(
      "O intervalo não pode exceder o espaço uint32.",
    );
  });

  it("incrementa calls exatamente uma vez em uma chamada válida", () => {
    const rng = createDeterministicRng("calls-int");
    const result = nextRandomInt(rng, 10, 20);

    expect(result.nextState.calls).toBe(rng.calls + 1);
  });

  it("não consome chamada nem modifica o estado com argumentos inválidos", () => {
    const rng = createDeterministicRng("invalid-does-not-consume");
    const snapshot = structuredClone(rng);

    expect(() => nextRandomInt(rng, 5, 5)).toThrow();
    expect(rng).toEqual(snapshot);
    expect(rng.calls).toBe(0);
  });
});

describe("imutabilidade e replay", () => {
  it("nenhuma função modifica o estado recebido", () => {
    const rng = createDeterministicRng("immutable-input");
    const snapshot = structuredClone(rng);

    nextRandomUint32(rng);
    nextRandomFloat(rng);
    nextRandomInt(rng, 0, 10);

    expect(rng).toEqual(snapshot);
  });

  it("estados retornados são estruturas independentes e imutáveis", () => {
    const initialState = createDeterministicRng("independent-states");
    const firstState = nextRandomUint32(initialState).nextState;
    const secondState = nextRandomUint32(firstState).nextState;

    expect(firstState).not.toBe(initialState);
    expect(secondState).not.toBe(firstState);
    expect(initialState.calls).toBe(0);
    expect(firstState.calls).toBe(1);
    expect(secondState.calls).toBe(2);
    expect(Object.isFrozen(initialState)).toBe(true);
    expect(Object.isFrozen(firstState)).toBe(true);
    expect(Object.isFrozen(secondState)).toBe(true);
  });

  it("continua a mesma sequência após serializar e desserializar o estado", () => {
    const initialState = createDeterministicRng("serialized-replay");
    const advancedState = nextRandomUint32(
      nextRandomUint32(initialState).nextState,
    ).nextState;
    const restoredState = JSON.parse(
      JSON.stringify(advancedState),
    ) as DeterministicRngState;

    expect(restoredState).toEqual(advancedState);
    expect(nextRandomUint32(restoredState)).toEqual(
      nextRandomUint32(advancedState),
    );
  });

  it("não depende de Math.random", () => {
    const mathRandom = vi.spyOn(Math, "random").mockImplementation(() => {
      throw new Error("Math.random não deve ser chamado.");
    });

    try {
      const rng = createDeterministicRng("without-math-random");

      nextRandomUint32(rng);
      nextRandomFloat(rng);
      nextRandomInt(rng, 0, 10);

      expect(mathRandom).not.toHaveBeenCalled();
    } finally {
      mathRandom.mockRestore();
    }
  });
});
