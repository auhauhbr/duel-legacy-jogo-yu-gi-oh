import type { BattleStep } from "./battle-step.js";
import type { DamageStepWindow } from "./damage-step-window.js";
import type { DuelPhase } from "./duel-phase.js";

export const STANDARD_PHASE_ORDER: readonly DuelPhase[] = Object.freeze([
  "DRAW",
  "STANDBY",
  "MAIN_1",
  "BATTLE",
  "MAIN_2",
  "END",
]);

export const BATTLE_STEP_ORDER: readonly BattleStep[] = Object.freeze([
  "START",
  "BATTLE",
  "DAMAGE",
  "END",
]);

export const DAMAGE_STEP_WINDOW_ORDER: readonly DamageStepWindow[] =
  Object.freeze([
    "START",
    "BEFORE_DAMAGE_CALCULATION",
    "DAMAGE_CALCULATION",
    "AFTER_DAMAGE_CALCULATION",
    "END",
  ]);
