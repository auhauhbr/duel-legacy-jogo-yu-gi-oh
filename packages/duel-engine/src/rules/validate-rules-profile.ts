import type {
  RulesProfile,
  RulesProfileQuantityField,
  RulesProfileValidationError,
  RulesProfileValidationResult,
  SummonMethod,
} from "./rules-profile.js";

const nonNegativeQuantityFields: readonly RulesProfileQuantityField[] = [
  "startingHandSize",
  "handLimit",
  "mainDeckMin",
  "mainDeckMax",
  "extraDeckMax",
  "sideDeckMax",
  "mainMonsterZones",
  "spellTrapZones",
];

export function validateRulesProfile(
  profile: RulesProfile,
): RulesProfileValidationResult {
  const errors: RulesProfileValidationError[] = [];

  if (profile.id.trim().length === 0) {
    errors.push({ code: "EMPTY_ID", field: "id" });
  }

  if (profile.startingLifePoints <= 0) {
    errors.push({
      code: "INVALID_STARTING_LIFE_POINTS",
      field: "startingLifePoints",
    });
  }

  for (const field of nonNegativeQuantityFields) {
    if (profile[field] < 0) {
      errors.push({ code: "NEGATIVE_QUANTITY", field });
    }
  }

  if (profile.handLimit < profile.startingHandSize) {
    errors.push({
      code: "HAND_LIMIT_BELOW_STARTING_HAND_SIZE",
      field: "handLimit",
    });
  }

  if (profile.mainDeckMin > profile.mainDeckMax) {
    errors.push({
      code: "MAIN_DECK_MIN_ABOVE_MAX",
      field: "mainDeckMin",
    });
  }

  if (profile.enabledSummons.length === 0) {
    errors.push({ code: "NO_ENABLED_SUMMONS", field: "enabledSummons" });
  }

  const seenSummonMethods = new Set<SummonMethod>();

  for (const method of profile.enabledSummons) {
    if (seenSummonMethods.has(method)) {
      errors.push({
        code: "DUPLICATE_SUMMON_METHOD",
        field: "enabledSummons",
        method,
      });
    }

    seenSummonMethods.add(method);
  }

  return {
    valid: errors.length === 0,
    errors,
  };
}
