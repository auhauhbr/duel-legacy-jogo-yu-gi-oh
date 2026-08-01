import { describe, expect, it } from "vitest";

import {
  gxLegacyProfile,
  validateRulesProfile,
  type RulesProfile,
  type RulesProfileQuantityField,
  type SummonMethod,
} from "../index.js";

const expectedSummonMethods: readonly SummonMethod[] = [
  "NORMAL",
  "TRIBUTE",
  "SET",
  "FLIP",
  "SPECIAL_BY_EFFECT",
  "RITUAL",
  "FUSION",
];

function createProfile(overrides: Partial<RulesProfile> = {}): RulesProfile {
  return {
    ...gxLegacyProfile,
    ...overrides,
    enabledSummons: overrides.enabledSummons ?? [
      ...gxLegacyProfile.enabledSummons,
    ],
  };
}

describe("gxLegacyProfile", () => {
  it("é um perfil válido", () => {
    expect(validateRulesProfile(gxLegacyProfile)).toEqual({
      valid: true,
      errors: [],
    });
  });

  it("contém exatamente os métodos de Invocação esperados", () => {
    expect(gxLegacyProfile.enabledSummons).toEqual(expectedSummonMethods);
    expect(gxLegacyProfile.enabledSummons).toHaveLength(7);
  });

  it("corresponde aos valores principais documentados", () => {
    expect(gxLegacyProfile).toMatchObject({
      id: "GX_LEGACY",
      startingLifePoints: 8000,
      startingHandSize: 5,
      handLimit: 6,
      mainDeckMin: 40,
      mainDeckMax: 60,
      extraDeckMax: 15,
      sideDeckMax: 0,
      mainMonsterZones: 5,
      spellTrapZones: 5,
      hasFieldZone: true,
      hasExtraMonsterZones: false,
      hasPendulumZones: false,
      hasSkillCard: false,
      drawOnFirstTurn: false,
      battleOnFirstTurn: false,
    });
  });
});

describe("validateRulesProfile", () => {
  it.each([0, -1])(
    "rejeita Pontos de Vida iniciais iguais a %i",
    (startingLifePoints) => {
      const result = validateRulesProfile(
        createProfile({ startingLifePoints }),
      );

      expect(result.valid).toBe(false);
      expect(result.errors).toContainEqual({
        code: "INVALID_STARTING_LIFE_POINTS",
        field: "startingLifePoints",
      });
    },
  );

  it("rejeita mão inicial negativa", () => {
    const result = validateRulesProfile(
      createProfile({ startingHandSize: -1 }),
    );

    expect(result.valid).toBe(false);
    expect(result.errors).toContainEqual({
      code: "NEGATIVE_QUANTITY",
      field: "startingHandSize",
    });
  });

  it("rejeita limite de mão menor que a mão inicial", () => {
    const result = validateRulesProfile(createProfile({ handLimit: 4 }));

    expect(result.valid).toBe(false);
    expect(result.errors).toContainEqual({
      code: "HAND_LIMIT_BELOW_STARTING_HAND_SIZE",
      field: "handLimit",
    });
  });

  it("rejeita Deck Principal mínimo maior que o máximo", () => {
    const result = validateRulesProfile(
      createProfile({ mainDeckMin: 61, mainDeckMax: 60 }),
    );

    expect(result.valid).toBe(false);
    expect(result.errors).toContainEqual({
      code: "MAIN_DECK_MIN_ABOVE_MAX",
      field: "mainDeckMin",
    });
  });

  const quantityFields: readonly RulesProfileQuantityField[] = [
    "startingHandSize",
    "handLimit",
    "mainDeckMin",
    "mainDeckMax",
    "extraDeckMax",
    "sideDeckMax",
    "mainMonsterZones",
    "spellTrapZones",
  ];

  it.each(quantityFields)("rejeita quantidade negativa em %s", (field) => {
    const profile = createProfile();
    const invalidProfile: RulesProfile = { ...profile, [field]: -1 };
    const result = validateRulesProfile(invalidProfile);

    expect(result.valid).toBe(false);
    expect(result.errors).toContainEqual({
      code: "NEGATIVE_QUANTITY",
      field,
    });
  });

  it("rejeita perfil sem método de Invocação habilitado", () => {
    const result = validateRulesProfile(createProfile({ enabledSummons: [] }));

    expect(result.valid).toBe(false);
    expect(result.errors).toContainEqual({
      code: "NO_ENABLED_SUMMONS",
      field: "enabledSummons",
    });
  });

  it.each(["", "   "])("rejeita ID vazio ou em branco", (id) => {
    const result = validateRulesProfile(createProfile({ id }));

    expect(result.valid).toBe(false);
    expect(result.errors).toContainEqual({ code: "EMPTY_ID", field: "id" });
  });

  it("detecta métodos de Invocação duplicados", () => {
    const result = validateRulesProfile(
      createProfile({ enabledSummons: ["NORMAL", "NORMAL"] }),
    );

    expect(result.valid).toBe(false);
    expect(result.errors).toContainEqual({
      code: "DUPLICATE_SUMMON_METHOD",
      field: "enabledSummons",
      method: "NORMAL",
    });
  });

  it("não modifica o perfil recebido", () => {
    const profile = createProfile();
    const snapshot = structuredClone(profile);

    validateRulesProfile(profile);

    expect(profile).toEqual(snapshot);
  });
});
