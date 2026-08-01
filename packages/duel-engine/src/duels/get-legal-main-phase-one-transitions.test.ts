import { describe, expect, it } from "vitest";

import {
  getLegalMainPhaseOneTransitions,
  gxLegacyProfile,
  transitionFromMainPhaseOne,
  type DuelPlayerState,
  type DuelState,
  type RulesProfile,
} from "../index.js";
import {
  createFirstTurnBattleProfile,
  createMainPhaseOneDuel,
  createMainPhaseOnePlayer,
  withDuplicateInArea,
  type CardArea,
} from "./main-phase-one-test-helpers.js";

describe("getLegalMainPhaseOneTransitions", () => {
  it("retorna somente END no turno 1 de GX_LEGACY", () => {
    expect(
      getLegalMainPhaseOneTransitions(
        createMainPhaseOneDuel(),
        gxLegacyProfile,
      ),
    ).toEqual(["END"]);
  });

  it.each([2, 3, 12])(
    "retorna BATTLE antes de END no turno %i de GX_LEGACY",
    (turnNumber) => {
      expect(
        getLegalMainPhaseOneTransitions(
          createMainPhaseOneDuel(turnNumber),
          gxLegacyProfile,
        ),
      ).toEqual(["BATTLE", "END"]);
    },
  );

  it("permite BATTLE no primeiro turno quando o perfil permite", () => {
    const profile = createFirstTurnBattleProfile();
    const duel: DuelState = {
      ...createMainPhaseOneDuel(),
      rulesProfileId: profile.id,
    };

    expect(getLegalMainPhaseOneTransitions(duel, profile)).toEqual([
      "BATTLE",
      "END",
    ]);
  });

  it("não retorna nenhuma outra fase", () => {
    const transitions = getLegalMainPhaseOneTransitions(
      createMainPhaseOneDuel(2),
      gxLegacyProfile,
    );

    expect(transitions).not.toContain("DRAW");
    expect(transitions).not.toContain("STANDBY");
    expect(transitions).not.toContain("MAIN_1");
    expect(transitions).not.toContain("MAIN_2");
  });

  it("retorna um novo array congelado em cada chamada", () => {
    const duel = createMainPhaseOneDuel(2);
    const first = getLegalMainPhaseOneTransitions(duel, gxLegacyProfile);
    const second = getLegalMainPhaseOneTransitions(duel, gxLegacyProfile);

    expect(first).not.toBe(second);
    expect(Object.isFrozen(first)).toBe(true);
    expect(Object.isFrozen(second)).toBe(true);
  });

  it("não modifica estado nem perfil e não consome RNG", () => {
    const duel = createMainPhaseOneDuel(2);
    const profile: RulesProfile = {
      ...gxLegacyProfile,
      enabledSummons: [...gxLegacyProfile.enabledSummons],
    };
    const duelSnapshot = structuredClone(duel);
    const profileSnapshot = structuredClone(profile);

    getLegalMainPhaseOneTransitions(duel, profile);

    expect(duel).toEqual(duelSnapshot);
    expect(profile).toEqual(profileSnapshot);
    expect(duel.rngState?.calls).toBe(duelSnapshot.rngState?.calls);
  });
});

const VALIDATED_OPERATIONS = [
  {
    name: "getLegalMainPhaseOneTransitions",
    execute: (duel: DuelState, profile: RulesProfile) =>
      getLegalMainPhaseOneTransitions(duel, profile),
  },
  {
    name: "transitionFromMainPhaseOne",
    execute: (duel: DuelState, profile: RulesProfile) =>
      transitionFromMainPhaseOne(duel, profile, "END"),
  },
] as const;

describe.each(VALIDATED_OPERATIONS)(
  "validações compartilhadas de $name",
  ({ execute }) => {
    it.each(["PREPARING", "FINISHED"] as const)(
      "rejeita status %s",
      (status) => {
        const invalid: DuelState = { ...createMainPhaseOneDuel(), status };

        expect(() => execute(invalid, gxLegacyProfile)).toThrow(
          "Somente um Duelo ACTIVE pode processar a Fase Principal 1.",
        );
      },
    );

    it.each(["DRAW", "STANDBY", "BATTLE", "MAIN_2", "END"] as const)(
      "rejeita phase %s",
      (phase) => {
        const invalid: DuelState = { ...createMainPhaseOneDuel(), phase };

        expect(() => execute(invalid, gxLegacyProfile)).toThrow(
          "O Duelo deve estar na fase MAIN_1.",
        );
      },
    );

    it.each([0, -1, -10, 1.5])(
      "rejeita turnNumber inválido %s",
      (turnNumber) => {
        const invalid: DuelState = {
          ...createMainPhaseOneDuel(),
          turnNumber,
        };

        expect(() => execute(invalid, gxLegacyProfile)).toThrow(
          "turnNumber deve ser um inteiro maior ou igual a 1.",
        );
      },
    );

    it("rejeita currentPlayerId null", () => {
      const invalid: DuelState = {
        ...createMainPhaseOneDuel(),
        currentPlayerId: null,
      };

      expect(() => execute(invalid, gxLegacyProfile)).toThrow(
        "A Fase Principal 1 deve possuir jogador atual.",
      );
    });

    it.each(["unknown-player", "player-2"])(
      "rejeita currentPlayerId incompatível %s",
      (currentPlayerId) => {
        const invalid: DuelState = {
          ...createMainPhaseOneDuel(),
          currentPlayerId,
        };

        expect(() => execute(invalid, gxLegacyProfile)).toThrow(
          "currentPlayerId é incompatível com turnOrder e com os jogadores.",
        );
      },
    );

    it("rejeita winnerId já definido", () => {
      const invalid: DuelState = {
        ...createMainPhaseOneDuel(),
        winnerId: "player-2",
      };

      expect(() => execute(invalid, gxLegacyProfile)).toThrow(
        "Um Duelo ACTIVE não pode possuir vencedor.",
      );
    });

    it("rejeita resultReason já definido", () => {
      const invalid: DuelState = {
        ...createMainPhaseOneDuel(),
        resultReason: "DECK_OUT",
      };

      expect(() => execute(invalid, gxLegacyProfile)).toThrow(
        "Um Duelo ACTIVE não pode possuir motivo de resultado.",
      );
    });

    it("rejeita rngState null", () => {
      const invalid: DuelState = {
        ...createMainPhaseOneDuel(),
        rngState: null,
      };

      expect(() => execute(invalid, gxLegacyProfile)).toThrow(
        "O Duelo deve possuir um estado de RNG.",
      );
    });

    it("rejeita RulesProfile inválido", () => {
      const invalidProfile: RulesProfile = {
        ...gxLegacyProfile,
        startingLifePoints: 0,
      };

      expect(() => execute(createMainPhaseOneDuel(), invalidProfile)).toThrow(
        "RulesProfile inválido.",
      );
    });

    it("rejeita RulesProfile incompatível", () => {
      const incompatibleProfile: RulesProfile = {
        ...gxLegacyProfile,
        id: "OTHER_PROFILE",
      };

      expect(() =>
        execute(createMainPhaseOneDuel(), incompatibleProfile),
      ).toThrow("RulesProfile incompatível com o Duelo.");
    });

    it.each([1, 3])("rejeita quantidade de jogadores igual a %i", (amount) => {
      const duel = createMainPhaseOneDuel();
      const players = [
        ...duel.players,
        createMainPhaseOnePlayer("player-3"),
      ].slice(0, amount);
      const invalid = { ...duel, players } as unknown as DuelState;

      expect(() => execute(invalid, gxLegacyProfile)).toThrow(
        "O Duelo deve possuir exatamente dois jogadores.",
      );
    });

    it("rejeita jogadores com IDs iguais", () => {
      const duel = createMainPhaseOneDuel();
      const duplicateIdPlayer: DuelPlayerState = {
        ...duel.players[1],
        playerId: duel.players[0].playerId,
      };
      const invalid: DuelState = {
        ...duel,
        players: [duel.players[0], duplicateIdPlayer],
      };

      expect(() => execute(invalid, gxLegacyProfile)).toThrow(
        "Os jogadores devem possuir IDs diferentes.",
      );
    });

    it.each([
      ["apenas um ID", ["player-1"]],
      ["três IDs", ["player-1", "player-2", "player-1"]],
      ["IDs repetidos", ["player-1", "player-1"]],
      ["jogador inexistente", ["player-1", "unknown-player"]],
    ])("rejeita turnOrder inválido: %s", (_scenario, turnOrder) => {
      const invalid = {
        ...createMainPhaseOneDuel(),
        turnOrder,
      } as unknown as DuelState;

      expect(() => execute(invalid, gxLegacyProfile)).toThrow(/turnOrder/);
    });

    it.each(["monsterZones", "spellTrapZones"] as const)(
      "rejeita quantidade incompatível em %s",
      (zoneField) => {
        const duel = createMainPhaseOneDuel();
        const invalidPlayer: DuelPlayerState = {
          ...duel.players[0],
          [zoneField]: [],
        };
        const invalid: DuelState = {
          ...duel,
          players: [invalidPlayer, duel.players[1]],
        };

        expect(() => execute(invalid, gxLegacyProfile)).toThrow(
          "As zonas do jogador são incompatíveis com o perfil.",
        );
      },
    );

    it.each([
      "mainDeck",
      "hand",
      "graveyard",
      "banishedFaceUp",
      "banishedFaceDown",
      "extraDeckFaceDown",
      "extraDeckFaceUp",
      "monsterZones",
      "spellTrapZones",
      "fieldZone",
    ] satisfies CardArea[])("rejeita ID global duplicado em %s", (area) => {
      const duel = createMainPhaseOneDuel();
      const duplicateId = duel.players[0].hand[0]!;
      const invalidSecondPlayer = withDuplicateInArea(
        duel.players[1],
        area,
        duplicateId,
      );
      const invalid: DuelState = {
        ...duel,
        players: [duel.players[0], invalidSecondPlayer],
      };

      expect(() => execute(invalid, gxLegacyProfile)).toThrow(
        "IDs de instância de carta devem ser únicos no Duelo.",
      );
    });

    it("erro não modifica estado nem perfil", () => {
      const invalid: DuelState = {
        ...createMainPhaseOneDuel(),
        turnNumber: 0,
      };
      const profile: RulesProfile = {
        ...gxLegacyProfile,
        enabledSummons: [...gxLegacyProfile.enabledSummons],
      };
      const stateSnapshot = structuredClone(invalid);
      const profileSnapshot = structuredClone(profile);

      expect(() => execute(invalid, profile)).toThrow();
      expect(invalid).toEqual(stateSnapshot);
      expect(profile).toEqual(profileSnapshot);
    });
  },
);
