import type { CardInstanceId, PlayerId } from "../identifiers/identifiers.js";
import type { DuelPlayerState } from "../players/duel-player-state.js";
import type { RulesProfile } from "../rules/rules-profile.js";

function getCardInstanceIds(player: DuelPlayerState): CardInstanceId[] {
  const occupiedMonsterZones = player.monsterZones.filter(
    (cardId): cardId is CardInstanceId => cardId !== null,
  );
  const occupiedSpellTrapZones = player.spellTrapZones.filter(
    (cardId): cardId is CardInstanceId => cardId !== null,
  );

  return [
    ...player.mainDeck,
    ...player.hand,
    ...player.graveyard,
    ...player.banishedFaceUp,
    ...player.banishedFaceDown,
    ...player.extraDeckFaceDown,
    ...player.extraDeckFaceUp,
    ...occupiedMonsterZones,
    ...occupiedSpellTrapZones,
    ...(player.fieldZone === null ? [] : [player.fieldZone]),
  ];
}

export function validatePlayerZones(
  players: readonly DuelPlayerState[],
  profile: RulesProfile,
): void {
  for (const player of players) {
    if (
      player.monsterZones.length !== profile.mainMonsterZones ||
      player.spellTrapZones.length !== profile.spellTrapZones
    ) {
      throw new Error("As zonas do jogador são incompatíveis com o perfil.");
    }
  }
}

export function validateExactlyTwoPlayers(
  players: readonly DuelPlayerState[],
): asserts players is readonly [DuelPlayerState, DuelPlayerState] {
  if (players.length !== 2 || !players[0] || !players[1]) {
    throw new Error("O Duelo deve possuir exatamente dois jogadores.");
  }

  if (players[0].playerId === players[1].playerId) {
    throw new Error("Os jogadores devem possuir IDs diferentes.");
  }
}

export function validateTurnOrder(
  players: readonly [DuelPlayerState, DuelPlayerState],
  turnOrder: readonly PlayerId[],
): asserts turnOrder is readonly [PlayerId, PlayerId] {
  if (turnOrder.length !== 2) {
    throw new Error("turnOrder deve conter exatamente dois jogadores.");
  }

  const playerIds = new Set(players.map(({ playerId }) => playerId));
  const turnOrderIds = new Set(turnOrder);

  if (
    turnOrderIds.size !== 2 ||
    turnOrder.some((playerId) => !playerIds.has(playerId)) ||
    playerIds.size !== turnOrderIds.size
  ) {
    throw new Error("turnOrder é incompatível com os jogadores do Duelo.");
  }
}

export function validateUniqueCardInstanceIds(
  players: readonly DuelPlayerState[],
): void {
  const allCardIds = players.flatMap(getCardInstanceIds);

  if (new Set(allCardIds).size !== allCardIds.length) {
    throw new Error("IDs de instância de carta devem ser únicos no Duelo.");
  }
}
