import type { DeterministicRngState } from "./deterministic-rng.js";
import { nextRandomInt } from "./deterministic-rng.js";

export interface ShuffleResult<T> {
  readonly items: readonly T[];
  readonly nextState: DeterministicRngState;
}

/**
 * Fisher–Yates do fim para o início. Cada iteração consome uma chamada do
 * RNG para escolher um índice no intervalo [0, currentIndex + 1).
 */
export function shuffleDeterministically<T>(
  items: readonly T[],
  rng: DeterministicRngState,
): ShuffleResult<T> {
  const shuffledItems = [...items];
  let nextState = rng;

  for (
    let currentIndex = shuffledItems.length - 1;
    currentIndex > 0;
    currentIndex -= 1
  ) {
    const randomResult = nextRandomInt(nextState, 0, currentIndex + 1);
    const randomIndex = randomResult.value;
    const currentItem = shuffledItems[currentIndex]!;

    shuffledItems[currentIndex] = shuffledItems[randomIndex]!;
    shuffledItems[randomIndex] = currentItem;
    nextState = randomResult.nextState;
  }

  return Object.freeze({
    items: Object.freeze(shuffledItems),
    nextState,
  });
}
