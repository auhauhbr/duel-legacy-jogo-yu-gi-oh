export type SummonMethod =
  | "NORMAL"
  | "TRIBUTE"
  | "SET"
  | "FLIP"
  | "SPECIAL_BY_EFFECT"
  | "RITUAL"
  | "FUSION";

export interface RulesProfile {
  readonly id: string;

  readonly startingLifePoints: number;
  readonly startingHandSize: number;
  readonly handLimit: number;

  readonly mainDeckMin: number;
  readonly mainDeckMax: number;
  readonly extraDeckMax: number;
  readonly sideDeckMax: number;

  readonly mainMonsterZones: number;
  readonly spellTrapZones: number;
  readonly hasFieldZone: boolean;
  readonly hasExtraMonsterZones: boolean;
  readonly hasPendulumZones: boolean;
  readonly hasSkillCard: boolean;

  readonly drawOnFirstTurn: boolean;
  readonly battleOnFirstTurn: boolean;

  readonly enabledSummons: readonly SummonMethod[];
}

export type RulesProfileQuantityField =
  | "startingHandSize"
  | "handLimit"
  | "mainDeckMin"
  | "mainDeckMax"
  | "extraDeckMax"
  | "sideDeckMax"
  | "mainMonsterZones"
  | "spellTrapZones";

export type RulesProfileValidationError =
  | { readonly code: "EMPTY_ID"; readonly field: "id" }
  | {
      readonly code: "INVALID_STARTING_LIFE_POINTS";
      readonly field: "startingLifePoints";
    }
  | {
      readonly code: "NEGATIVE_QUANTITY";
      readonly field: RulesProfileQuantityField;
    }
  | {
      readonly code: "HAND_LIMIT_BELOW_STARTING_HAND_SIZE";
      readonly field: "handLimit";
    }
  | {
      readonly code: "MAIN_DECK_MIN_ABOVE_MAX";
      readonly field: "mainDeckMin";
    }
  | {
      readonly code: "NO_ENABLED_SUMMONS";
      readonly field: "enabledSummons";
    }
  | {
      readonly code: "DUPLICATE_SUMMON_METHOD";
      readonly field: "enabledSummons";
      readonly method: SummonMethod;
    };

export interface RulesProfileValidationResult {
  readonly valid: boolean;
  readonly errors: readonly RulesProfileValidationError[];
}
