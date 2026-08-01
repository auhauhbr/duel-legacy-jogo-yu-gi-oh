import type { RulesProfile, SummonMethod } from "./rules-profile.js";

const enabledSummons: readonly SummonMethod[] = Object.freeze([
  "NORMAL",
  "TRIBUTE",
  "SET",
  "FLIP",
  "SPECIAL_BY_EFFECT",
  "RITUAL",
  "FUSION",
]);

export const gxLegacyProfile: RulesProfile = Object.freeze({
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

  enabledSummons,
});
