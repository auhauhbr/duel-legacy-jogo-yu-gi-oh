<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine;

use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Duels\DuelState;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Players\DrawCardsResult;
use DuelLegacy\DuelEngine\Players\DuelPlayerState;
use DuelLegacy\DuelEngine\Random\DeterministicRngState;
use DuelLegacy\DuelEngine\Random\RandomResult;
use DuelLegacy\DuelEngine\Random\ShuffleResult;
use DuelLegacy\DuelEngine\Rules\RulesProfile;
use DuelLegacy\DuelEngine\Rules\RulesProfiles;
use DuelLegacy\DuelEngine\Rules\RulesProfileValidationResult;

function duelEngineVersion(): string
{
    return Engine::VERSION;
}

function gxLegacyProfile(): RulesProfile
{
    return RulesProfiles::gxLegacy();
}

function validateRulesProfile(RulesProfile $profile): RulesProfileValidationResult
{
    return Engine::validateRulesProfile($profile);
}

/**
 * @param  list<CardInstance>  $mainDeck
 * @param  list<CardInstance>  $extraDeck
 */
function createInitialPlayerState(RulesProfile $profile, string $playerId, array $mainDeck, array $extraDeck): DuelPlayerState
{
    return Engine::createInitialPlayerState($profile, $playerId, $mainDeck, $extraDeck);
}

/** @param list<DuelPlayerState> $players */
function createInitialDuelState(string $duelId, RulesProfile $profile, string $engineVersion, string $cardPoolVersion, array $players, string $firstPlayerId): DuelState
{
    return Engine::createInitialDuelState($duelId, $profile, $engineVersion, $cardPoolVersion, $players, $firstPlayerId);
}

function createDeterministicRng(string $seed): DeterministicRngState
{
    return Engine::createDeterministicRng($seed);
}

/** @return RandomResult<int> */
function nextRandomUint32(DeterministicRngState $rng): RandomResult
{
    return Engine::nextRandomUint32($rng);
}

/** @return RandomResult<float> */
function nextRandomFloat(DeterministicRngState $rng): RandomResult
{
    return Engine::nextRandomFloat($rng);
}

/** @return RandomResult<int> */
function nextRandomInt(DeterministicRngState $rng, int|float $minInclusive, int|float $maxExclusive): RandomResult
{
    return Engine::nextRandomInt($rng, $minInclusive, $maxExclusive);
}

/**
 * @template T
 *
 * @param  list<T>  $items
 * @return ShuffleResult<T>
 */
function shuffleDeterministically(array $items, DeterministicRngState $rng): ShuffleResult
{
    return Engine::shuffleDeterministically($items, $rng);
}

function drawCardsFromMainDeck(DuelPlayerState $playerState, int|float $amount): DrawCardsResult
{
    return Engine::drawCardsFromMainDeck($playerState, $amount);
}

function prepareInitialDuelState(DuelState $duelState, RulesProfile $profile, string $seed): DuelState
{
    return Engine::prepareInitialDuelState($duelState, $profile, $seed);
}

function startFirstTurn(DuelState $duelState, RulesProfile $profile): DuelState
{
    return Engine::startFirstTurn($duelState, $profile);
}

function processDrawPhase(DuelState $duelState, RulesProfile $profile): DuelState
{
    return Engine::processDrawPhase($duelState, $profile);
}

function processStandbyPhase(DuelState $duelState, RulesProfile $profile): DuelState
{
    return Engine::processStandbyPhase($duelState, $profile);
}

/** @return list<DuelPhase> */
function getLegalMainPhaseOneTransitions(DuelState $duelState, RulesProfile $profile): array
{
    return Engine::getLegalMainPhaseOneTransitions($duelState, $profile);
}

function transitionFromMainPhaseOne(DuelState $duelState, RulesProfile $profile, DuelPhase $targetPhase): DuelState
{
    return Engine::transitionFromMainPhaseOne($duelState, $profile, $targetPhase);
}

function startNextTurn(DuelState $duelState, RulesProfile $profile): DuelState
{
    return Engine::startNextTurn($duelState, $profile);
}

function getRequiredEndPhaseDiscardCount(DuelState $duelState, RulesProfile $profile): int
{
    return Engine::getRequiredEndPhaseDiscardCount($duelState, $profile);
}

/** @param list<string> $selectedCardInstanceIds */
function discardEndPhaseHandExcess(DuelState $duelState, RulesProfile $profile, array $selectedCardInstanceIds): DuelState
{
    return Engine::discardEndPhaseHandExcess($duelState, $profile, $selectedCardInstanceIds);
}

/** @param list<string> $selectedCardInstanceIds */
function processEndPhase(DuelState $duelState, RulesProfile $profile, array $selectedCardInstanceIds): DuelState
{
    return Engine::processEndPhase($duelState, $profile, $selectedCardInstanceIds);
}

function getNextStandardPhase(DuelPhase $currentPhase): ?DuelPhase
{
    return Engine::getNextStandardPhase($currentPhase);
}

function isValidStandardPhaseTransition(DuelPhase $currentPhase, DuelPhase $nextPhase): bool
{
    return Engine::isValidStandardPhaseTransition($currentPhase, $nextPhase);
}
