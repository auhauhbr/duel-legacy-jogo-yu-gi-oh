<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine;

use DomainException;
use DuelLegacy\DuelEngine\Cards\CardInstance;
use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Duels\DuelResultReason;
use DuelLegacy\DuelEngine\Duels\DuelState;
use DuelLegacy\DuelEngine\Duels\DuelStatus;
use DuelLegacy\DuelEngine\Identifiers\CardInstanceId;
use DuelLegacy\DuelEngine\Internal\EcmaScriptString;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Phases\PhaseOrder;
use DuelLegacy\DuelEngine\Players\DrawCardsResult;
use DuelLegacy\DuelEngine\Players\DuelPlayerState;
use DuelLegacy\DuelEngine\Random\DeterministicRngState;
use DuelLegacy\DuelEngine\Random\RandomResult;
use DuelLegacy\DuelEngine\Random\ShuffleResult;
use DuelLegacy\DuelEngine\Rules\RulesProfile;
use DuelLegacy\DuelEngine\Rules\RulesProfileQuantityField;
use DuelLegacy\DuelEngine\Rules\RulesProfileValidationError;
use DuelLegacy\DuelEngine\Rules\RulesProfileValidationResult;
use DuelLegacy\DuelEngine\Zones\MonsterZones;
use DuelLegacy\DuelEngine\Zones\OrderedCardZone;
use DuelLegacy\DuelEngine\Zones\PlayerCardZones;
use DuelLegacy\DuelEngine\Zones\PlayerCardZonesDrawer;
use DuelLegacy\DuelEngine\Zones\PlayerCardZonesHandExcessDiscarder;
use DuelLegacy\DuelEngine\Zones\SpellTrapZones;
use InvalidArgumentException;

final class Engine
{
    public const string VERSION = '0.0.0';

    private const int FNV_OFFSET_BASIS_32 = 0x811C9DC5;

    private const int FNV_PRIME_32 = 0x01000193;

    private const int NON_ZERO_STATE_FALLBACK = 0x9E3779B9;

    private const int UINT32_MASK = 0xFFFFFFFF;

    private const float UINT32_RANGE = 4294967296.0;

    public static function validateRulesProfile(RulesProfile $profile): RulesProfileValidationResult
    {
        /** @var list<RulesProfileValidationError> $errors */
        $errors = [];

        if (EcmaScriptString::isBlank($profile->id)) {
            $errors[] = RulesProfileValidationError::emptyId();
        }

        if ($profile->startingLifePoints <= 0) {
            $errors[] = RulesProfileValidationError::invalidStartingLifePoints();
        }

        foreach (RulesProfileQuantityField::cases() as $field) {
            if ($profile->{$field->value} < 0) {
                $errors[] = RulesProfileValidationError::negativeQuantity($field);
            }
        }

        if ($profile->handLimit < $profile->startingHandSize) {
            $errors[] = RulesProfileValidationError::handLimitBelowStartingHandSize();
        }

        if ($profile->mainDeckMin > $profile->mainDeckMax) {
            $errors[] = RulesProfileValidationError::mainDeckMinAboveMax();
        }

        if ($profile->enabledSummons === []) {
            $errors[] = RulesProfileValidationError::noEnabledSummons();
        }

        $seen = [];
        foreach ($profile->enabledSummons as $method) {
            if (isset($seen[$method->value])) {
                $errors[] = RulesProfileValidationError::duplicateSummonMethod($method);
            }
            $seen[$method->value] = true;
        }

        return new RulesProfileValidationResult($errors === [], $errors);
    }

    /**
     * @param  list<CardInstance>  $mainDeck
     * @param  list<CardInstance>  $extraDeck
     */
    public static function createInitialPlayerState(
        RulesProfile $profile,
        string $playerId,
        array $mainDeck,
        array $extraDeck,
    ): DuelPlayerState {
        self::assertValidProfile($profile);

        if (EcmaScriptString::isBlank($playerId)) {
            throw new InvalidArgumentException('playerId não pode ser vazio.');
        }

        self::assertCardInstanceList($mainDeck, 'mainDeck');
        self::assertCardInstanceList($extraDeck, 'extraDeck');

        $allCardIds = array_map(
            static fn (CardInstance $card): string => $card->id->value,
            [...$mainDeck, ...$extraDeck],
        );
        $seenCardIds = [];
        foreach ($allCardIds as $cardId) {
            $key = "id:{$cardId}";
            if (isset($seenCardIds[$key])) {
                throw new InvalidArgumentException('IDs de instância de carta devem ser únicos.');
            }
            $seenCardIds[$key] = true;
        }

        if (count($extraDeck) > $profile->extraDeckMax) {
            throw new InvalidArgumentException('Deck Adicional excede o limite do perfil.');
        }

        return new DuelPlayerState(
            playerId: $playerId,
            lifePoints: (int) $profile->startingLifePoints,
            cardZones: new PlayerCardZones(
                mainDeck: new OrderedCardZone(CardLocation::MAIN_DECK, $mainDeck),
                hand: new OrderedCardZone(CardLocation::HAND),
                graveyard: new OrderedCardZone(CardLocation::GRAVEYARD),
                banishedFaceUp: new OrderedCardZone(CardLocation::BANISHED_FACE_UP),
                banishedFaceDown: new OrderedCardZone(CardLocation::BANISHED_FACE_DOWN),
                extraDeckFaceDown: new OrderedCardZone(CardLocation::EXTRA_DECK_FACE_DOWN, $extraDeck),
                extraDeckFaceUp: new OrderedCardZone(CardLocation::EXTRA_DECK_FACE_UP),
            ),
            monsterZones: MonsterZones::empty((int) $profile->mainMonsterZones),
            spellTrapZones: SpellTrapZones::empty((int) $profile->spellTrapZones),
            fieldZone: null,
            normalSummonsUsed: 0,
            normalSummonLimit: 1,
        );
    }

    /** @param list<DuelPlayerState> $players */
    public static function createInitialDuelState(
        string $duelId,
        RulesProfile $profile,
        string $engineVersion,
        string $cardPoolVersion,
        array $players,
        string $firstPlayerId,
    ): DuelState {
        if (EcmaScriptString::isBlank($duelId)) {
            throw new InvalidArgumentException('duelId não pode ser vazio.');
        }
        self::assertValidProfile($profile);
        if (EcmaScriptString::isBlank($engineVersion)) {
            throw new InvalidArgumentException('engineVersion não pode ser vazia.');
        }
        if (EcmaScriptString::isBlank($cardPoolVersion)) {
            throw new InvalidArgumentException('cardPoolVersion não pode ser vazia.');
        }

        self::validateExactlyTwoPlayers($players);
        [$firstPlayer, $secondPlayer] = $players;

        if ($firstPlayerId !== $firstPlayer->playerId && $firstPlayerId !== $secondPlayer->playerId) {
            throw new InvalidArgumentException('O jogador inicial deve pertencer ao Duelo.');
        }

        self::validatePlayerZones($players, $profile);
        self::validateUniqueCardInstanceIds($players);
        $turnOrder = $firstPlayerId === $firstPlayer->playerId
            ? [$firstPlayer->playerId, $secondPlayer->playerId]
            : [$secondPlayer->playerId, $firstPlayer->playerId];

        return new DuelState(
            duelId: $duelId,
            rulesProfileId: $profile->id,
            engineVersion: $engineVersion,
            cardPoolVersion: $cardPoolVersion,
            players: [self::clonePlayer($firstPlayer), self::clonePlayer($secondPlayer)],
            turnOrder: $turnOrder,
            rngState: null,
            status: DuelStatus::PREPARING,
            turnNumber: 0,
            currentPlayerId: null,
            phase: null,
            winnerId: null,
            resultReason: null,
        );
    }

    public static function createDeterministicRng(string $seed): DeterministicRngState
    {
        if (EcmaScriptString::isBlank($seed)) {
            throw new InvalidArgumentException('A seed não pode ser vazia.');
        }

        return new DeterministicRngState($seed, self::hashSeed($seed), 0);
    }

    /** @return RandomResult<int> */
    public static function nextRandomUint32(DeterministicRngState $rng): RandomResult
    {
        $value = $rng->state & self::UINT32_MASK;
        $value = ($value ^ (($value << 13) & self::UINT32_MASK)) & self::UINT32_MASK;
        $value = ($value ^ ($value >> 17)) & self::UINT32_MASK;
        $value = ($value ^ (($value << 5) & self::UINT32_MASK)) & self::UINT32_MASK;

        return new RandomResult($value, new DeterministicRngState($rng->seed, $value, $rng->calls + 1));
    }

    /** @return RandomResult<float> */
    public static function nextRandomFloat(DeterministicRngState $rng): RandomResult
    {
        $result = self::nextRandomUint32($rng);

        return new RandomResult($result->value / self::UINT32_RANGE, $result->nextState);
    }

    /** @return RandomResult<int> */
    public static function nextRandomInt(
        DeterministicRngState $rng,
        int|float $minInclusive,
        int|float $maxExclusive,
    ): RandomResult {
        if (! self::isSafeInteger($minInclusive) || ! self::isSafeInteger($maxExclusive)) {
            throw new InvalidArgumentException('Os limites devem ser inteiros seguros.');
        }
        if ($minInclusive >= $maxExclusive) {
            throw new InvalidArgumentException('minInclusive deve ser menor que maxExclusive.');
        }

        $intervalSize = $maxExclusive - $minInclusive;
        if ($intervalSize > self::UINT32_RANGE) {
            throw new InvalidArgumentException('O intervalo não pode exceder o espaço uint32.');
        }

        $result = self::nextRandomUint32($rng);
        $offset = (int) floor(($result->value / self::UINT32_RANGE) * $intervalSize);

        return new RandomResult((int) $minInclusive + $offset, $result->nextState);
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return ShuffleResult<T>
     */
    public static function shuffleDeterministically(array $items, DeterministicRngState $rng): ShuffleResult
    {
        $shuffledItems = [...$items];
        $nextState = $rng;

        for ($currentIndex = count($shuffledItems) - 1; $currentIndex > 0; $currentIndex--) {
            $randomResult = self::nextRandomInt($nextState, 0, $currentIndex + 1);
            $randomIndex = (int) $randomResult->value;
            [$shuffledItems[$currentIndex], $shuffledItems[$randomIndex]] = [$shuffledItems[$randomIndex], $shuffledItems[$currentIndex]];
            $nextState = $randomResult->nextState;
        }

        return new ShuffleResult(array_values($shuffledItems), $nextState);
    }

    public static function drawCardsFromMainDeck(DuelPlayerState $playerState, int|float $amount): DrawCardsResult
    {
        if (! is_finite((float) $amount) || floor((float) $amount) !== (float) $amount) {
            throw new InvalidArgumentException('A quantidade de compra deve ser um inteiro finito.');
        }
        if ($amount < 0) {
            throw new InvalidArgumentException('A quantidade de compra não pode ser negativa.');
        }
        if ($amount > $playerState->cardZones->mainDeck->count()) {
            throw new InvalidArgumentException('O Deck Principal não possui cartas suficientes.');
        }

        $integerAmount = (int) $amount;
        $cardZones = $playerState->cardZones;
        $drawn = [];
        $drawer = new PlayerCardZonesDrawer;
        for ($index = 0; $index < $integerAmount; $index++) {
            $drawn[] = $cardZones->mainDeck->cards()[0]->id->value;
            $cardZones = $drawer->draw($cardZones);
        }
        $nextPlayer = self::clonePlayer($playerState)->with(['cardZones' => $cardZones]);

        return new DrawCardsResult($nextPlayer, $drawn);
    }

    public static function prepareInitialDuelState(DuelState $duelState, RulesProfile $profile, string $seed): DuelState
    {
        if ($duelState->status !== DuelStatus::PREPARING) {
            throw new DomainException('Somente um Duelo em PREPARING pode ser preparado.');
        }
        if ($duelState->rngState !== null) {
            throw new DomainException('O Duelo já possui um estado de RNG.');
        }
        self::assertProfileMatches($duelState, $profile);
        if (EcmaScriptString::isBlank($seed)) {
            throw new InvalidArgumentException('A seed não pode ser vazia.');
        }
        self::validateCoreState($duelState, $profile);
        foreach ($duelState->players as $player) {
            if ($player->cardZones->mainDeck->count() < $profile->startingHandSize) {
                throw new DomainException('Jogador sem cartas suficientes para a mão inicial.');
            }
        }

        $rngState = self::createDeterministicRng($seed);
        $firstPlayer = self::findPlayer($duelState, $duelState->turnOrder[0]);
        $firstShuffle = self::shuffleDeterministically($firstPlayer->cardZones->mainDeck->cards(), $rngState);
        $secondPlayer = self::findPlayer($duelState, $duelState->turnOrder[1]);
        $secondShuffle = self::shuffleDeterministically($secondPlayer->cardZones->mainDeck->cards(), $firstShuffle->nextState);

        $preparedFirst = self::drawCardsFromMainDeck(
            self::clonePlayer($firstPlayer)->with(['cardZones' => $firstPlayer->cardZones->withZone(new OrderedCardZone(CardLocation::MAIN_DECK, $firstShuffle->items))]),
            $profile->startingHandSize,
        )->playerState;
        $preparedSecond = self::drawCardsFromMainDeck(
            self::clonePlayer($secondPlayer)->with(['cardZones' => $secondPlayer->cardZones->withZone(new OrderedCardZone(CardLocation::MAIN_DECK, $secondShuffle->items))]),
            $profile->startingHandSize,
        )->playerState;
        $players = $duelState->players[0]->playerId === $preparedFirst->playerId
            ? [$preparedFirst, $preparedSecond]
            : [$preparedSecond, $preparedFirst];

        return $duelState->with([
            'players' => $players,
            'turnOrder' => [...$duelState->turnOrder],
            'rngState' => $secondShuffle->nextState,
        ]);
    }

    public static function startFirstTurn(DuelState $duelState, RulesProfile $profile): DuelState
    {
        if ($duelState->status !== DuelStatus::PREPARING) {
            throw new DomainException('Somente um Duelo em PREPARING pode ser iniciado.');
        }
        if ($duelState->rngState === null) {
            throw new DomainException('O Duelo deve possuir um estado de RNG.');
        }
        if ($duelState->turnNumber !== 0) {
            throw new DomainException('O Duelo preparado deve estar antes do primeiro turno.');
        }
        if ($duelState->currentPlayerId !== null) {
            throw new DomainException('O Duelo preparado não pode possuir jogador atual.');
        }
        if ($duelState->phase !== null) {
            throw new DomainException('O Duelo preparado não pode possuir fase atual.');
        }
        if ($duelState->winnerId !== null) {
            throw new DomainException('O Duelo preparado não pode possuir vencedor.');
        }
        if ($duelState->resultReason !== null) {
            throw new DomainException('O Duelo preparado não pode possuir motivo de resultado.');
        }
        self::assertProfileMatches($duelState, $profile);
        self::validateCoreState($duelState, $profile);
        foreach ($duelState->players as $player) {
            if ($player->cardZones->hand->count() !== $profile->startingHandSize) {
                throw new DomainException('A mão inicial é incompatível com o perfil.');
            }
        }

        return self::copyState($duelState, [
            'status' => DuelStatus::ACTIVE,
            'turnNumber' => 1,
            'currentPlayerId' => $duelState->turnOrder[0],
            'phase' => DuelPhase::DRAW,
        ]);
    }

    public static function processDrawPhase(DuelState $duelState, RulesProfile $profile): DuelState
    {
        self::validateActiveState(
            $duelState,
            $profile,
            DuelPhase::DRAW,
            'Somente um Duelo ACTIVE pode processar a Fase de Compra.',
            'O Duelo deve estar na fase DRAW.',
            'A Fase de Compra deve possuir jogador atual.',
        );

        $players = array_map(self::clonePlayer(...), $duelState->players);
        $currentPlayerId = $duelState->currentPlayerId;
        if ($currentPlayerId === null) {
            throw new DomainException('A Fase de Compra deve possuir jogador atual.');
        }
        $currentIndex = self::findPlayerIndex($players, $currentPlayerId);
        $shouldDraw = $duelState->turnNumber > 1 || $profile->drawOnFirstTurn;

        if (! $shouldDraw) {
            return self::copyState($duelState, ['players' => $players, 'phase' => DuelPhase::STANDBY]);
        }

        if ($players[$currentIndex]->cardZones->mainDeck->isEmpty()) {
            $winner = $players[1 - $currentIndex];

            return self::copyState($duelState, [
                'players' => $players,
                'status' => DuelStatus::FINISHED,
                'winnerId' => $winner->playerId,
                'resultReason' => DuelResultReason::DECK_OUT,
            ]);
        }

        $players[$currentIndex] = self::drawCardsFromMainDeck($players[$currentIndex], 1)->playerState;

        return self::copyState($duelState, ['players' => $players, 'phase' => DuelPhase::STANDBY]);
    }

    public static function processStandbyPhase(DuelState $duelState, RulesProfile $profile): DuelState
    {
        self::validateActiveState(
            $duelState,
            $profile,
            DuelPhase::STANDBY,
            'Somente um Duelo ACTIVE pode processar a Fase de Apoio.',
            'O Duelo deve estar na fase STANDBY.',
            'A Fase de Apoio deve possuir jogador atual.',
        );

        return self::copyState($duelState, ['phase' => DuelPhase::MAIN_1]);
    }

    /** @return list<DuelPhase> */
    public static function getLegalMainPhaseOneTransitions(DuelState $duelState, RulesProfile $profile): array
    {
        self::validateActiveState(
            $duelState,
            $profile,
            DuelPhase::MAIN_1,
            'Somente um Duelo ACTIVE pode processar a Fase Principal 1.',
            'O Duelo deve estar na fase MAIN_1.',
            'A Fase Principal 1 deve possuir jogador atual.',
        );

        $transitions = [];
        if ($duelState->turnNumber > 1 || $profile->battleOnFirstTurn) {
            $transitions[] = DuelPhase::BATTLE;
        }
        $transitions[] = DuelPhase::END;

        return $transitions;
    }

    public static function transitionFromMainPhaseOne(
        DuelState $duelState,
        RulesProfile $profile,
        DuelPhase $targetPhase,
    ): DuelState {
        $legal = self::getLegalMainPhaseOneTransitions($duelState, $profile);
        if (! in_array($targetPhase, $legal, true)) {
            throw new DomainException("Transição MAIN_1 → {$targetPhase->value} não permitida.");
        }

        return self::copyState($duelState, ['phase' => $targetPhase]);
    }

    public static function startNextTurn(DuelState $duelState, RulesProfile $profile): DuelState
    {
        self::validateActiveState(
            $duelState,
            $profile,
            DuelPhase::END,
            'Somente um Duelo ACTIVE pode iniciar o próximo turno.',
            'O Duelo deve estar na fase END.',
            'A Fase Final deve possuir jogador atual.',
        );

        $nextTurnNumber = (int) $duelState->turnNumber + 1;
        $nextPlayerId = $duelState->turnOrder[($nextTurnNumber - 1) % 2];
        $players = array_map(
            static fn (DuelPlayerState $player): DuelPlayerState => self::clonePlayer($player)->with([
                'normalSummonsUsed' => $player->playerId === $nextPlayerId ? 0 : $player->normalSummonsUsed,
            ]),
            $duelState->players,
        );

        return self::copyState($duelState, [
            'players' => $players,
            'turnNumber' => $nextTurnNumber,
            'currentPlayerId' => $nextPlayerId,
            'phase' => DuelPhase::DRAW,
        ]);
    }

    public static function getRequiredEndPhaseDiscardCount(DuelState $duelState, RulesProfile $profile): int
    {
        self::validateActiveState(
            $duelState,
            $profile,
            DuelPhase::END,
            'Somente um Duelo ACTIVE pode consultar o descarte.',
            'O Duelo deve estar na fase END.',
            'A Fase Final deve possuir jogador atual.',
        );
        if (! self::isInteger($profile->handLimit)) {
            throw new InvalidArgumentException('RulesProfile inválido.');
        }

        $currentPlayerId = $duelState->currentPlayerId;
        if ($currentPlayerId === null) {
            throw new DomainException('A Fase Final deve possuir jogador atual.');
        }
        $player = self::findPlayer($duelState, $currentPlayerId);

        return max(0, $player->cardZones->hand->count() - (int) $profile->handLimit);
    }

    /** @param list<string> $selectedCardInstanceIds */
    public static function discardEndPhaseHandExcess(
        DuelState $duelState,
        RulesProfile $profile,
        array $selectedCardInstanceIds,
    ): DuelState {
        self::assertValidProfile($profile);
        self::validateCoreState($duelState, $profile);
        self::validateActiveState(
            $duelState,
            $profile,
            DuelPhase::END,
            'Somente um Duelo ACTIVE pode descartar o excesso da mão na Fase Final.',
            'O Duelo deve estar na fase END.',
            'A Fase Final deve possuir jogador atual.',
        );

        $currentPlayerId = $duelState->currentPlayerId;
        if ($currentPlayerId === null) {
            throw new DomainException('A Fase Final deve possuir jogador atual.');
        }
        $currentPlayer = self::findPlayer($duelState, $currentPlayerId);
        $requiredCount = self::getRequiredEndPhaseDiscardCount($duelState, $profile);
        $selectedCount = count($selectedCardInstanceIds);
        if ($selectedCount !== $requiredCount) {
            throw new InvalidArgumentException(
                "A quantidade de cartas selecionadas para descarte deve ser exatamente {$requiredCount}; recebida: {$selectedCount}.",
            );
        }

        $validatedSelection = [];
        $typedSelection = [];
        foreach ($selectedCardInstanceIds as $selectedCardInstanceId) {
            if (EcmaScriptString::isBlank($selectedCardInstanceId)) {
                throw new InvalidArgumentException('IDs selecionados para descarte não podem ser vazios.');
            }
            if (in_array($selectedCardInstanceId, $validatedSelection, true)) {
                throw new InvalidArgumentException('IDs selecionados para descarte devem ser únicos.');
            }
            $cardInstanceId = new CardInstanceId($selectedCardInstanceId);
            if (! $currentPlayer->cardZones->hand->contains($cardInstanceId)) {
                throw new InvalidArgumentException("A carta selecionada não está na mão do jogador atual: {$selectedCardInstanceId}.");
            }
            $validatedSelection[] = $selectedCardInstanceId;
            $typedSelection[] = $cardInstanceId;
        }

        $cardZones = (new PlayerCardZonesHandExcessDiscarder)->discardExcess(
            $currentPlayer->cardZones,
            (int) $profile->handLimit,
            $typedSelection,
        );

        $players = array_map(self::clonePlayer(...), $duelState->players);
        $currentPlayerIndex = self::findPlayerIndex($players, $currentPlayerId);
        $players[$currentPlayerIndex] = $players[$currentPlayerIndex]->with(['cardZones' => $cardZones]);

        return self::copyState($duelState, ['players' => $players]);
    }

    /** @param list<string> $selectedCardInstanceIds */
    public static function processEndPhase(
        DuelState $duelState,
        RulesProfile $profile,
        array $selectedCardInstanceIds,
    ): DuelState {
        $stateAfterDiscard = self::discardEndPhaseHandExcess($duelState, $profile, $selectedCardInstanceIds);

        return self::startNextTurn($stateAfterDiscard, $profile);
    }

    public static function getNextStandardPhase(DuelPhase $currentPhase): ?DuelPhase
    {
        $order = PhaseOrder::standard();
        $index = array_search($currentPhase, $order, true);

        return $index === false ? null : ($order[$index + 1] ?? null);
    }

    public static function isValidStandardPhaseTransition(DuelPhase $currentPhase, DuelPhase $nextPhase): bool
    {
        $valid = [
            DuelPhase::DRAW->value => [DuelPhase::STANDBY],
            DuelPhase::STANDBY->value => [DuelPhase::MAIN_1],
            DuelPhase::MAIN_1->value => [DuelPhase::BATTLE, DuelPhase::END],
            DuelPhase::BATTLE->value => [DuelPhase::MAIN_2, DuelPhase::END],
            DuelPhase::MAIN_2->value => [DuelPhase::END],
            DuelPhase::END->value => [],
        ];

        return in_array($nextPhase, $valid[$currentPhase->value], true);
    }

    private static function assertValidProfile(RulesProfile $profile): void
    {
        if (! self::validateRulesProfile($profile)->valid) {
            throw new InvalidArgumentException('RulesProfile inválido.');
        }
    }

    private static function assertProfileMatches(DuelState $duelState, RulesProfile $profile): void
    {
        self::assertValidProfile($profile);
        if ($profile->id !== $duelState->rulesProfileId) {
            throw new InvalidArgumentException('RulesProfile incompatível com o Duelo.');
        }
    }

    /** @param list<DuelPlayerState> $players */
    private static function validateExactlyTwoPlayers(array $players): void
    {
        if (count($players) !== 2) {
            throw new InvalidArgumentException('O Duelo deve possuir exatamente dois jogadores.');
        }
        if ($players[0]->playerId === $players[1]->playerId) {
            throw new InvalidArgumentException('Os jogadores devem possuir IDs diferentes.');
        }
    }

    /** @param list<DuelPlayerState> $players */
    private static function validatePlayerZones(array $players, RulesProfile $profile): void
    {
        foreach ($players as $player) {
            if ($player->monsterZones->capacity() !== $profile->mainMonsterZones || $player->spellTrapZones->capacity() !== $profile->spellTrapZones) {
                throw new InvalidArgumentException('As zonas do jogador são incompatíveis com o perfil.');
            }
        }
    }

    /** @param list<DuelPlayerState> $players */
    private static function validateUniqueCardInstanceIds(array $players): void
    {
        $seenIds = [];
        foreach ($players as $player) {
            $ids = [
                ...array_map(static fn (CardInstance $card): string => $card->id->value, array_merge(...array_map(static fn (OrderedCardZone $zone): array => $zone->cards(), $player->cardZones->zones()))),
                ...array_map(
                    static fn (CardInstance $card): string => $card->id->value,
                    array_values(array_filter(
                        $player->monsterZones->slots(),
                        static fn (?CardInstance $card): bool => $card !== null,
                    )),
                ),
                ...array_map(
                    static fn (CardInstance $card): string => $card->id->value,
                    array_values(array_filter(
                        $player->spellTrapZones->slots(),
                        static fn (?CardInstance $card): bool => $card !== null,
                    )),
                ),
                ...($player->fieldZone === null ? [] : [$player->fieldZone]),
            ];
            foreach ($ids as $id) {
                $key = "id:{$id}";
                if (isset($seenIds[$key])) {
                    throw new InvalidArgumentException('IDs de instância de carta devem ser únicos no Duelo.');
                }
                $seenIds[$key] = true;
            }
        }
    }

    private static function validateTurnOrder(DuelState $duelState): void
    {
        if (count($duelState->turnOrder) !== 2 || count(array_unique($duelState->turnOrder, SORT_STRING)) !== 2) {
            throw new InvalidArgumentException('turnOrder deve conter exatamente dois jogadores.');
        }
        $playerIds = array_map(static fn (DuelPlayerState $player): string => $player->playerId, $duelState->players);
        $actual = $duelState->turnOrder;
        sort($playerIds);
        sort($actual);
        if ($actual !== $playerIds) {
            throw new InvalidArgumentException('turnOrder é incompatível com os jogadores do Duelo.');
        }
    }

    private static function validateCoreState(DuelState $duelState, RulesProfile $profile): void
    {
        self::validateExactlyTwoPlayers($duelState->players);
        self::validateTurnOrder($duelState);
        self::validatePlayerZones($duelState->players, $profile);
        self::validateUniqueCardInstanceIds($duelState->players);
    }

    private static function validateActiveState(
        DuelState $duelState,
        RulesProfile $profile,
        DuelPhase $expectedPhase,
        string $statusMessage,
        string $phaseMessage,
        string $currentPlayerMessage,
    ): void {
        if ($duelState->status !== DuelStatus::ACTIVE) {
            throw new DomainException($statusMessage);
        }
        if ($duelState->phase !== $expectedPhase) {
            throw new DomainException($phaseMessage);
        }
        if (! self::isInteger($duelState->turnNumber) || $duelState->turnNumber < 1) {
            throw new DomainException('turnNumber deve ser um inteiro maior ou igual a 1.');
        }
        if ($duelState->currentPlayerId === null) {
            throw new DomainException($currentPlayerMessage);
        }
        if ($duelState->winnerId !== null) {
            throw new DomainException('Um Duelo ACTIVE não pode possuir vencedor.');
        }
        if ($duelState->resultReason !== null) {
            throw new DomainException('Um Duelo ACTIVE não pode possuir motivo de resultado.');
        }
        if ($duelState->rngState === null) {
            throw new DomainException('O Duelo deve possuir um estado de RNG.');
        }
        self::assertProfileMatches($duelState, $profile);
        self::validateCoreState($duelState, $profile);

        $expectedPlayerId = $duelState->turnOrder[((int) $duelState->turnNumber - 1) % 2];
        if ($duelState->currentPlayerId !== $expectedPlayerId || ! in_array($duelState->currentPlayerId, array_map(static fn (DuelPlayerState $player): string => $player->playerId, $duelState->players), true)) {
            throw new DomainException('currentPlayerId é incompatível com turnOrder e com os jogadores.');
        }
    }

    private static function clonePlayer(DuelPlayerState $player): DuelPlayerState
    {
        return new DuelPlayerState(
            playerId: $player->playerId,
            lifePoints: $player->lifePoints,
            cardZones: $player->cardZones,
            monsterZones: $player->monsterZones,
            spellTrapZones: $player->spellTrapZones,
            fieldZone: $player->fieldZone,
            normalSummonsUsed: $player->normalSummonsUsed,
            normalSummonLimit: $player->normalSummonLimit,
        );
    }

    /** @param array<array-key, mixed> $cards */
    private static function assertCardInstanceList(array $cards, string $name): void
    {
        if (! array_is_list($cards)) {
            throw new InvalidArgumentException("{$name} deve ser uma lista.");
        }
        foreach ($cards as $card) {
            if (! $card instanceof CardInstance) {
                throw new InvalidArgumentException("{$name} deve conter apenas CardInstance.");
            }
        }
    }

    /** @param array<string, mixed> $changes */
    private static function copyState(DuelState $state, array $changes = []): DuelState
    {
        $players = $changes['players'] ?? array_map(self::clonePlayer(...), $state->players);
        $rng = array_key_exists('rngState', $changes) ? $changes['rngState'] : $state->rngState;
        if ($rng instanceof DeterministicRngState) {
            $rng = new DeterministicRngState($rng->seed, $rng->state, $rng->calls);
        }

        return $state->with([
            ...$changes,
            'players' => $players,
            'turnOrder' => [...($changes['turnOrder'] ?? $state->turnOrder)],
            'rngState' => $rng,
        ]);
    }

    private static function findPlayer(DuelState $duelState, string $playerId): DuelPlayerState
    {
        foreach ($duelState->players as $player) {
            if ($player->playerId === $playerId) {
                return $player;
            }
        }
        throw new DomainException('turnOrder é incompatível com os jogadores do Duelo.');
    }

    /** @param list<DuelPlayerState> $players */
    private static function findPlayerIndex(array $players, string $playerId): int
    {
        foreach ($players as $index => $player) {
            if ($player->playerId === $playerId) {
                return $index;
            }
        }
        throw new DomainException('currentPlayerId é incompatível com turnOrder e com os jogadores.');
    }

    private static function isInteger(int|float $value): bool
    {
        return is_int($value) || (is_finite($value) && floor($value) === $value);
    }

    private static function isSafeInteger(int|float $value): bool
    {
        return self::isInteger($value) && abs((float) $value) <= 9007199254740991.0;
    }

    private static function hashSeed(string $seed): int
    {
        $hash = self::FNV_OFFSET_BASIS_32;
        foreach (self::utf16CodeUnits($seed) as $codeUnit) {
            $hash = (($hash ^ ($codeUnit & 0xFF)) * self::FNV_PRIME_32) & self::UINT32_MASK;
            $hash = (($hash ^ ($codeUnit >> 8)) * self::FNV_PRIME_32) & self::UINT32_MASK;
        }

        return $hash === 0 ? self::NON_ZERO_STATE_FALLBACK : $hash;
    }

    /** @return list<int> */
    private static function utf16CodeUnits(string $value): array
    {
        $units = [];
        $length = strlen($value);
        for ($index = 0; $index < $length;) {
            $first = ord($value[$index]);
            if ($first < 0x80) {
                $codePoint = $first;
                $index++;
            } elseif (($first & 0xE0) === 0xC0 && $index + 1 < $length) {
                $codePoint = (($first & 0x1F) << 6) | (ord($value[$index + 1]) & 0x3F);
                $index += 2;
            } elseif (($first & 0xF0) === 0xE0 && $index + 2 < $length) {
                $codePoint = (($first & 0x0F) << 12) | ((ord($value[$index + 1]) & 0x3F) << 6) | (ord($value[$index + 2]) & 0x3F);
                $index += 3;
            } elseif (($first & 0xF8) === 0xF0 && $index + 3 < $length) {
                $codePoint = (($first & 0x07) << 18) | ((ord($value[$index + 1]) & 0x3F) << 12) | ((ord($value[$index + 2]) & 0x3F) << 6) | (ord($value[$index + 3]) & 0x3F);
                $index += 4;
            } else {
                $codePoint = 0xFFFD;
                $index++;
            }

            if ($codePoint <= 0xFFFF) {
                $units[] = $codePoint;
            } else {
                $adjusted = $codePoint - 0x10000;
                $units[] = 0xD800 + ($adjusted >> 10);
                $units[] = 0xDC00 + ($adjusted & 0x3FF);
            }
        }

        return $units;
    }
}
