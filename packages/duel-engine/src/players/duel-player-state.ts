import type { CardInstanceId, PlayerId } from "../identifiers/identifiers.js";

export interface DuelPlayerState {
  readonly playerId: PlayerId;
  readonly lifePoints: number;

  readonly mainDeck: CardInstanceId[];
  readonly hand: CardInstanceId[];
  readonly graveyard: CardInstanceId[];
  readonly banishedFaceUp: CardInstanceId[];
  readonly banishedFaceDown: CardInstanceId[];
  readonly extraDeckFaceDown: CardInstanceId[];
  readonly extraDeckFaceUp: CardInstanceId[];

  readonly monsterZones: Array<CardInstanceId | null>;
  readonly spellTrapZones: Array<CardInstanceId | null>;
  readonly fieldZone: CardInstanceId | null;

  readonly normalSummonsUsed: number;
  readonly normalSummonLimit: number;
}
