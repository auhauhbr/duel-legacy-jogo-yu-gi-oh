import { describe, expect, it } from "vitest";

import {
  gxLegacyProfile,
  transitionFromMainPhaseOne,
  type DuelPhase,
  type DuelState,
  type RulesProfile,
} from "../index.js";
import {
  createFirstTurnBattleProfile,
  createMainPhaseOneDuel,
  PLAYER_ARRAY_FIELDS,
} from "./main-phase-one-test-helpers.js";

describe("transitionFromMainPhaseOne", () => {
  it.each([
    [1, "END"],
    [2, "BATTLE"],
    [2, "END"],
  ] as const)(
    "aceita no turno %i a transição MAIN_1 → %s de GX_LEGACY",
    (turnNumber, targetPhase) => {
      const duel = createMainPhaseOneDuel(turnNumber);
      const result = transitionFromMainPhaseOne(
        duel,
        gxLegacyProfile,
        targetPhase,
      );

      expect(result).toEqual({ ...duel, phase: targetPhase });
      expect(result).not.toBe(duel);
      expect(result.status).toBe("ACTIVE");
    },
  );

  it("rejeita BATTLE no primeiro turno de GX_LEGACY", () => {
    expect(() =>
      transitionFromMainPhaseOne(
        createMainPhaseOneDuel(),
        gxLegacyProfile,
        "BATTLE",
      ),
    ).toThrow("Transição MAIN_1 → BATTLE não permitida.");
  });

  it("permite BATTLE no primeiro turno quando o perfil permite", () => {
    const profile = createFirstTurnBattleProfile();
    const duel: DuelState = {
      ...createMainPhaseOneDuel(),
      rulesProfileId: profile.id,
    };

    expect(transitionFromMainPhaseOne(duel, profile, "BATTLE").phase).toBe(
      "BATTLE",
    );
  });

  it.each(["BATTLE", "END"] as const)(
    "altera somente phase para %s",
    (targetPhase) => {
      const duel = createMainPhaseOneDuel(2);
      const result = transitionFromMainPhaseOne(
        duel,
        gxLegacyProfile,
        targetPhase,
      );

      expect(result).toEqual({ ...duel, phase: targetPhase });
      expect(result.turnNumber).toBe(duel.turnNumber);
      expect(result.currentPlayerId).toBe(duel.currentPlayerId);
      expect(result.winnerId).toBeNull();
      expect(result.resultReason).toBeNull();
      expect(result.turnOrder).toEqual(duel.turnOrder);
      expect(result.duelId).toBe(duel.duelId);
      expect(result.rulesProfileId).toBe(duel.rulesProfileId);
      expect(result.engineVersion).toBe(duel.engineVersion);
      expect(result.cardPoolVersion).toBe(duel.cardPoolVersion);
    },
  );

  it("preserva jogadores, áreas, Pontos de Vida e contadores", () => {
    const duel = createMainPhaseOneDuel(2);
    const result = transitionFromMainPhaseOne(duel, gxLegacyProfile, "BATTLE");

    for (const playerIndex of [0, 1] as const) {
      const inputPlayer = duel.players[playerIndex];
      const outputPlayer = result.players[playerIndex];

      expect(outputPlayer).toEqual(inputPlayer);
      expect(outputPlayer.lifePoints).toBe(inputPlayer.lifePoints);
      expect(outputPlayer.normalSummonsUsed).toBe(
        inputPlayer.normalSummonsUsed,
      );
      expect(outputPlayer.normalSummonLimit).toBe(
        inputPlayer.normalSummonLimit,
      );
      expect(outputPlayer.fieldZone).toBe(inputPlayer.fieldZone);

      for (const field of PLAYER_ARRAY_FIELDS) {
        expect(outputPlayer[field]).toEqual(inputPlayer[field]);
      }
    }
  });

  it("preserva RNG sem consumir chamadas", () => {
    const duel = createMainPhaseOneDuel(2);
    const result = transitionFromMainPhaseOne(duel, gxLegacyProfile, "BATTLE");

    expect(result.rngState).toEqual(duel.rngState);
    expect(result.rngState?.calls).toBe(duel.rngState?.calls);
    expect(result.rngState?.seed).toBe(duel.rngState?.seed);
    expect(result.rngState?.state).toBe(duel.rngState?.state);
  });

  it("não modifica estado, perfil, jogadores, arrays ou RNG recebidos", () => {
    const duel = createMainPhaseOneDuel(2);
    const profile: RulesProfile = {
      ...gxLegacyProfile,
      enabledSummons: [...gxLegacyProfile.enabledSummons],
    };
    const duelSnapshot = structuredClone(duel);
    const profileSnapshot = structuredClone(profile);

    transitionFromMainPhaseOne(duel, profile, "END");

    expect(duel).toEqual(duelSnapshot);
    expect(profile).toEqual(profileSnapshot);
  });

  it("saída não compartilha arrays mutáveis nem objetos internos", () => {
    const duel = createMainPhaseOneDuel(2);
    const result = transitionFromMainPhaseOne(duel, gxLegacyProfile, "END");

    expect(result.players).not.toBe(duel.players);
    expect(result.turnOrder).not.toBe(duel.turnOrder);
    expect(result.rngState).not.toBe(duel.rngState);

    for (const playerIndex of [0, 1] as const) {
      expect(result.players[playerIndex]).not.toBe(duel.players[playerIndex]);

      for (const field of PLAYER_ARRAY_FIELDS) {
        expect(result.players[playerIndex][field]).not.toBe(
          duel.players[playerIndex][field],
        );
      }
    }
  });

  it("congela estado e todas as estruturas retornadas", () => {
    const result = transitionFromMainPhaseOne(
      createMainPhaseOneDuel(2),
      gxLegacyProfile,
      "END",
    );

    expect(Object.isFrozen(result)).toBe(true);
    expect(Object.isFrozen(result.players)).toBe(true);
    expect(Object.isFrozen(result.turnOrder)).toBe(true);
    expect(Object.isFrozen(result.rngState)).toBe(true);

    for (const player of result.players) {
      expect(Object.isFrozen(player)).toBe(true);
      for (const field of PLAYER_ARRAY_FIELDS) {
        expect(Object.isFrozen(player[field])).toBe(true);
      }
    }
  });

  it.each(["DRAW", "STANDBY", "MAIN_1", "MAIN_2"] satisfies DuelPhase[])(
    "rejeita targetPhase %s",
    (targetPhase) => {
      expect(() =>
        transitionFromMainPhaseOne(
          createMainPhaseOneDuel(2),
          gxLegacyProfile,
          targetPhase,
        ),
      ).toThrow(`Transição MAIN_1 → ${targetPhase} não permitida.`);
    },
  );

  it("erro de targetPhase não modifica a entrada", () => {
    const duel = createMainPhaseOneDuel();
    const snapshot = structuredClone(duel);

    expect(() =>
      transitionFromMainPhaseOne(duel, gxLegacyProfile, "BATTLE"),
    ).toThrow();
    expect(duel).toEqual(snapshot);
  });
});
