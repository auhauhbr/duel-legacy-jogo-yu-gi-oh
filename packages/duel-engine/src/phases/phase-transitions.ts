import type { DuelPhase } from "./duel-phase.js";
import { STANDARD_PHASE_ORDER } from "./phase-order.js";

const validStandardPhaseTransitions: Readonly<
  Record<DuelPhase, readonly DuelPhase[]>
> = Object.freeze({
  DRAW: Object.freeze(["STANDBY"] as const),
  STANDBY: Object.freeze(["MAIN_1"] as const),
  MAIN_1: Object.freeze(["BATTLE", "END"] as const),
  BATTLE: Object.freeze(["MAIN_2", "END"] as const),
  MAIN_2: Object.freeze(["END"] as const),
  END: Object.freeze([] as const),
});

export function getNextStandardPhase(
  currentPhase: DuelPhase,
): DuelPhase | null {
  const currentIndex = STANDARD_PHASE_ORDER.indexOf(currentPhase);

  return STANDARD_PHASE_ORDER[currentIndex + 1] ?? null;
}

export function isValidStandardPhaseTransition(
  currentPhase: DuelPhase,
  nextPhase: DuelPhase,
): boolean {
  return validStandardPhaseTransitions[currentPhase].includes(nextPhase);
}
