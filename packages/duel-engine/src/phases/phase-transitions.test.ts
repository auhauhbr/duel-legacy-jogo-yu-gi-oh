import { describe, expect, it } from "vitest";

import {
  BATTLE_STEP_ORDER,
  DAMAGE_STEP_WINDOW_ORDER,
  getNextStandardPhase,
  isValidStandardPhaseTransition,
  STANDARD_PHASE_ORDER,
  type DuelPhase,
} from "../index.js";

type IsReadonlyArray<T extends readonly unknown[]> = T extends unknown[]
  ? false
  : true;

const validTransitions: ReadonlyArray<
  readonly [currentPhase: DuelPhase, nextPhase: DuelPhase]
> = [
  ["DRAW", "STANDBY"],
  ["STANDBY", "MAIN_1"],
  ["MAIN_1", "BATTLE"],
  ["MAIN_1", "END"],
  ["BATTLE", "MAIN_2"],
  ["BATTLE", "END"],
  ["MAIN_2", "END"],
];

describe("ordens estruturais das fases", () => {
  it("contém exatamente as seis fases na ordem padrão", () => {
    expect(STANDARD_PHASE_ORDER).toEqual([
      "DRAW",
      "STANDBY",
      "MAIN_1",
      "BATTLE",
      "MAIN_2",
      "END",
    ]);
    expect(STANDARD_PHASE_ORDER).toHaveLength(6);
  });

  it("expõe o array de fases como readonly e congelado", () => {
    const phaseOrderIsReadonly: IsReadonlyArray<typeof STANDARD_PHASE_ORDER> =
      true;

    expect(phaseOrderIsReadonly).toBe(true);
    expect(Object.isFrozen(STANDARD_PHASE_ORDER)).toBe(true);
  });

  it("mantém a ordem correta das etapas de batalha", () => {
    expect(BATTLE_STEP_ORDER).toEqual(["START", "BATTLE", "DAMAGE", "END"]);
    expect(Object.isFrozen(BATTLE_STEP_ORDER)).toBe(true);
  });

  it("mantém a ordem correta das janelas da Etapa de Dano", () => {
    expect(DAMAGE_STEP_WINDOW_ORDER).toEqual([
      "START",
      "BEFORE_DAMAGE_CALCULATION",
      "DAMAGE_CALCULATION",
      "AFTER_DAMAGE_CALCULATION",
      "END",
    ]);
    expect(Object.isFrozen(DAMAGE_STEP_WINDOW_ORDER)).toBe(true);
  });
});

describe("getNextStandardPhase", () => {
  it.each([
    ["DRAW", "STANDBY"],
    ["STANDBY", "MAIN_1"],
    ["MAIN_1", "BATTLE"],
    ["BATTLE", "MAIN_2"],
    ["MAIN_2", "END"],
  ] as const)("retorna a fase seguinte de %s", (currentPhase, nextPhase) => {
    expect(getNextStandardPhase(currentPhase)).toBe(nextPhase);
  });

  it("retorna null depois da Fase Final", () => {
    expect(getNextStandardPhase("END")).toBeNull();
  });
});

describe("isValidStandardPhaseTransition", () => {
  it.each(validTransitions)(
    "aceita a transição válida de %s para %s",
    (currentPhase, nextPhase) => {
      expect(isValidStandardPhaseTransition(currentPhase, nextPhase)).toBe(
        true,
      );
    },
  );

  it.each([
    ["STANDBY", "DRAW"],
    ["MAIN_1", "STANDBY"],
    ["BATTLE", "MAIN_1"],
    ["MAIN_2", "BATTLE"],
    ["END", "MAIN_2"],
  ] as const)(
    "rejeita a transição para fase anterior de %s para %s",
    (currentPhase, nextPhase) => {
      expect(isValidStandardPhaseTransition(currentPhase, nextPhase)).toBe(
        false,
      );
    },
  );

  it.each(STANDARD_PHASE_ORDER)(
    "rejeita a permanência em %s",
    (currentPhase) => {
      expect(isValidStandardPhaseTransition(currentPhase, currentPhase)).toBe(
        false,
      );
    },
  );

  it("rejeita DRAW para MAIN_1", () => {
    expect(isValidStandardPhaseTransition("DRAW", "MAIN_1")).toBe(false);
  });

  it("rejeita MAIN_1 para MAIN_2", () => {
    expect(isValidStandardPhaseTransition("MAIN_1", "MAIN_2")).toBe(false);
  });

  it.each(STANDARD_PHASE_ORDER)("não permite sair de END para %s", (phase) => {
    expect(isValidStandardPhaseTransition("END", phase)).toBe(false);
  });

  it("não modifica argumentos nem constantes", () => {
    const phaseOrderSnapshot = [...STANDARD_PHASE_ORDER];
    const battleStepOrderSnapshot = [...BATTLE_STEP_ORDER];
    const damageStepWindowOrderSnapshot = [...DAMAGE_STEP_WINDOW_ORDER];
    const currentPhase: DuelPhase = "MAIN_1";
    const nextPhase: DuelPhase = "END";

    getNextStandardPhase(currentPhase);
    isValidStandardPhaseTransition(currentPhase, nextPhase);

    expect(currentPhase).toBe("MAIN_1");
    expect(nextPhase).toBe("END");
    expect(STANDARD_PHASE_ORDER).toEqual(phaseOrderSnapshot);
    expect(BATTLE_STEP_ORDER).toEqual(battleStepOrderSnapshot);
    expect(DAMAGE_STEP_WINDOW_ORDER).toEqual(damageStepWindowOrderSnapshot);
  });
});
