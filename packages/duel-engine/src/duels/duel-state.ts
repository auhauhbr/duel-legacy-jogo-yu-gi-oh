import type { DuelId, PlayerId } from "../identifiers/identifiers.js";
import type { DuelPlayerState } from "../players/duel-player-state.js";
import type { DuelPhase } from "../phases/duel-phase.js";
import type { DeterministicRngState } from "../random/deterministic-rng.js";
import type { DuelStatus } from "./duel-status.js";

export interface DuelState {
  readonly duelId: DuelId;
  readonly rulesProfileId: string;
  readonly engineVersion: string;
  readonly cardPoolVersion: string;

  readonly players: readonly [DuelPlayerState, DuelPlayerState];
  readonly turnOrder: readonly [PlayerId, PlayerId];
  readonly rngState: DeterministicRngState | null;

  readonly status: DuelStatus;
  readonly turnNumber: number;
  readonly currentPlayerId: PlayerId | null;
  readonly phase: DuelPhase | null;
  readonly winnerId: PlayerId | null;
  readonly resultReason: string | null;
}
