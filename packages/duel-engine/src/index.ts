export const duelEngineVersion = "0.0.0";

export type { CardLocation } from "./cards/card-location.js";
export type { CardPosition } from "./cards/card-position.js";
export { createInitialDuelState } from "./duels/create-initial-duel-state.js";
export type { DuelState } from "./duels/duel-state.js";
export type { DuelStatus } from "./duels/duel-status.js";
export type {
  CardInstanceId,
  DuelId,
  PlayerId,
} from "./identifiers/identifiers.js";
export type { BattleStep } from "./phases/battle-step.js";
export type { DamageStepWindow } from "./phases/damage-step-window.js";
export type { DuelPhase } from "./phases/duel-phase.js";
export {
  BATTLE_STEP_ORDER,
  DAMAGE_STEP_WINDOW_ORDER,
  STANDARD_PHASE_ORDER,
} from "./phases/phase-order.js";
export {
  getNextStandardPhase,
  isValidStandardPhaseTransition,
} from "./phases/phase-transitions.js";
export { createInitialPlayerState } from "./players/create-initial-player-state.js";
export type { DuelPlayerState } from "./players/duel-player-state.js";
export { gxLegacyProfile } from "./rules/gx-legacy.js";
export { validateRulesProfile } from "./rules/validate-rules-profile.js";
export type {
  RulesProfile,
  RulesProfileQuantityField,
  RulesProfileValidationError,
  RulesProfileValidationResult,
  SummonMethod,
} from "./rules/rules-profile.js";
