<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DomainException;
use DuelLegacy\DuelEngine\Duels\DuelState;
use DuelLegacy\DuelEngine\Duels\DuelStatus;
use DuelLegacy\DuelEngine\Engine;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Rules\RulesProfile;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\discardEndPhaseHandExcess;
use function DuelLegacy\DuelEngine\gxLegacyProfile;
use function DuelLegacy\DuelEngine\processEndPhase;
use function DuelLegacy\DuelEngine\startNextTurn;

final class ProcessEndPhaseTest extends TestCase
{
    /** @return iterable<string, array{int, string}> */
    public static function turnsWithoutDiscard(): iterable
    {
        yield 'jogador 1' => [1, 'player-2'];
        yield 'jogador 2' => [2, 'player-1'];
    }

    #[DataProvider('turnsWithoutDiscard')]
    public function test_processes_end_phase_without_discard_for_both_players(int $turnNumber, string $nextPlayerId): void
    {
        $duel = TestFactory::endDuel($turnNumber);
        $players = array_map(
            static fn ($player) => $player->with([
                'normalSummonsUsed' => $player->playerId === 'player-1' ? 3 : 4,
            ]),
            $duel->players,
        );
        $duel = $duel->with(['players' => $players]);
        $snapshot = $duel->toArray();

        $result = processEndPhase($duel, gxLegacyProfile(), []);

        self::assertNotSame($duel, $result);
        self::assertSame($snapshot, $duel->toArray());
        self::assertSame(DuelStatus::ACTIVE, $result->status);
        self::assertSame(DuelPhase::DRAW, $result->phase);
        self::assertSame($turnNumber + 1, $result->turnNumber);
        self::assertSame($nextPlayerId, $result->currentPlayerId);
        self::assertSame($duel->turnOrder, $result->turnOrder);
        self::assertNull($result->winnerId);
        self::assertNull($result->resultReason);
        self::assertSame($duel->rngState?->toArray(), $result->rngState?->toArray());
        self::assertNotSame($duel->rngState, $result->rngState);

        foreach ([0, 1] as $index) {
            self::assertSame($duel->players[$index]->mainDeck, $result->players[$index]->mainDeck);
            self::assertSame($duel->players[$index]->hand, $result->players[$index]->hand);
            self::assertSame($duel->players[$index]->graveyard, $result->players[$index]->graveyard);
            self::assertSame(
                $duel->players[$index]->playerId === $nextPlayerId ? 0 : $duel->players[$index]->normalSummonsUsed,
                $result->players[$index]->normalSummonsUsed,
            );
        }
    }

    /**
     * @return iterable<string, array{int, RulesProfile, list<string>, list<string>, list<string>, list<string>}>
     */
    public static function validDiscardScenarios(): iterable
    {
        yield 'jogador 1 descarta uma carta' => [
            1,
            gxLegacyProfile(),
            ['A', 'B', 'C', 'D', 'E', 'F', 'G'],
            ['D'],
            ['A', 'B', 'C', 'E', 'F', 'G'],
            ['old', 'D'],
        ];
        yield 'jogador 2 descarta duas cartas em ordem inversa' => [
            2,
            gxLegacyProfile(),
            ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'],
            ['H', 'B'],
            ['A', 'C', 'D', 'E', 'F', 'G'],
            ['old', 'B', 'H'],
        ];
        yield 'limite personalizado e três descartes' => [
            1,
            TestFactory::profile(['startingHandSize' => 7, 'handLimit' => 7]),
            ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'],
            ['J', 'B', 'F'],
            ['A', 'C', 'D', 'E', 'G', 'H', 'I'],
            ['old', 'B', 'F', 'J'],
        ];
    }

    /**
     * @param  list<string>  $hand
     * @param  list<string>  $selection
     * @param  list<string>  $expectedHand
     * @param  list<string>  $expectedGraveyard
     */
    #[DataProvider('validDiscardScenarios')]
    public function test_discards_before_starting_next_turn(
        int $turnNumber,
        RulesProfile $profile,
        array $hand,
        array $selection,
        array $expectedHand,
        array $expectedGraveyard,
    ): void {
        $duel = $this->withCurrentPlayer($turnNumber, $hand, ['graveyard' => ['old']]);
        $players = array_map(
            static fn ($player) => $player->with([
                'normalSummonsUsed' => $player->playerId === 'player-1' ? 3 : 4,
            ]),
            $duel->players,
        );
        $duel = $duel->with(['players' => $players]);
        $snapshot = $duel->toArray();
        $selectionSnapshot = $selection;
        $currentPlayerIndex = $turnNumber % 2 === 1 ? 0 : 1;
        $nextPlayerIndex = 1 - $currentPlayerIndex;

        $result = processEndPhase($duel, $profile, $selection);

        self::assertSame($snapshot, $duel->toArray());
        self::assertSame($selectionSnapshot, $selection);
        self::assertSame($expectedHand, $result->players[$currentPlayerIndex]->hand);
        self::assertSame($expectedGraveyard, $result->players[$currentPlayerIndex]->graveyard);
        self::assertSame($duel->players[$currentPlayerIndex]->mainDeck, $result->players[$currentPlayerIndex]->mainDeck);
        self::assertSame($duel->players[$nextPlayerIndex]->mainDeck, $result->players[$nextPlayerIndex]->mainDeck);
        self::assertSame($duel->players[$nextPlayerIndex]->hand, $result->players[$nextPlayerIndex]->hand);
        self::assertSame($duel->players[$nextPlayerIndex]->graveyard, $result->players[$nextPlayerIndex]->graveyard);
        self::assertSame(0, $result->players[$nextPlayerIndex]->normalSummonsUsed);
        self::assertSame($duel->players[$currentPlayerIndex]->normalSummonsUsed, $result->players[$currentPlayerIndex]->normalSummonsUsed);
        self::assertSame(DuelPhase::DRAW, $result->phase);
        self::assertSame($turnNumber + 1, $result->turnNumber);
        self::assertSame($duel->turnOrder[$nextPlayerIndex], $result->currentPlayerId);
        self::assertSame($duel->turnOrder, $result->turnOrder);
        self::assertSame(DuelStatus::ACTIVE, $result->status);
        self::assertNull($result->winnerId);
        self::assertNull($result->resultReason);
        self::assertSame($duel->rngState?->toArray(), $result->rngState?->toArray());
    }

    /** @return iterable<string, array{list<string>, list<string>, string}> */
    public static function invalidSelections(): iterable
    {
        yield 'vazia com excesso' => [range('A', 'G'), [], 'A quantidade de cartas selecionadas para descarte deve ser exatamente 1; recebida: 0.'];
        yield 'menor que a necessária' => [range('A', 'H'), ['A'], 'A quantidade de cartas selecionadas para descarte deve ser exatamente 2; recebida: 1.'];
        yield 'maior que a necessária' => [range('A', 'G'), ['A', 'B'], 'A quantidade de cartas selecionadas para descarte deve ser exatamente 1; recebida: 2.'];
        yield 'não vazia sem excesso' => [range('A', 'F'), ['A'], 'A quantidade de cartas selecionadas para descarte deve ser exatamente 0; recebida: 1.'];
        yield 'ID vazio' => [range('A', 'G'), [''], 'IDs selecionados para descarte não podem ser vazios.'];
        yield 'whitespace ECMAScript' => [range('A', 'G'), ["\u{FEFF}"], 'IDs selecionados para descarte não podem ser vazios.'];
        yield 'ID duplicado' => [range('A', 'H'), ['B', 'B'], 'IDs selecionados para descarte devem ser únicos.'];
        yield 'ID inexistente' => [range('A', 'G'), ['missing-card'], 'A carta selecionada não está na mão do jogador atual: missing-card.'];
        yield 'ID fora da mão' => [range('A', 'G'), ['player-1-main-1'], 'A carta selecionada não está na mão do jogador atual: player-1-main-1.'];
        yield 'ID do adversário' => [range('A', 'G'), ['player-2-hand'], 'A carta selecionada não está na mão do jogador atual: player-2-hand.'];
    }

    /**
     * @param  list<string>  $hand
     * @param  list<string>  $selection
     */
    #[DataProvider('invalidSelections')]
    public function test_propagates_invalid_selection_without_advancing_or_mutating(
        array $hand,
        array $selection,
        string $message,
    ): void {
        $duel = $this->withCurrentPlayer(1, $hand);

        $this->assertFailureIsAtomic($duel, gxLegacyProfile(), $selection, $message, InvalidArgumentException::class);
    }

    /** @return iterable<string, array{DuelState, RulesProfile, string, class-string<\Throwable>}> */
    public static function invalidStates(): iterable
    {
        $end = TestFactory::endDuel();

        yield 'Duel PREPARING' => [
            $end->with(['status' => DuelStatus::PREPARING]),
            gxLegacyProfile(),
            'Somente um Duelo ACTIVE pode descartar o excesso da mão na Fase Final.',
            DomainException::class,
        ];
        yield 'Duel FINISHED' => [
            $end->with(['status' => DuelStatus::FINISHED]),
            gxLegacyProfile(),
            'Somente um Duelo ACTIVE pode descartar o excesso da mão na Fase Final.',
            DomainException::class,
        ];
        foreach ([DuelPhase::DRAW, DuelPhase::STANDBY, DuelPhase::MAIN_1, DuelPhase::BATTLE] as $phase) {
            yield "fase {$phase->value}" => [
                $end->with(['phase' => $phase]),
                gxLegacyProfile(),
                'O Duelo deve estar na fase END.',
                DomainException::class,
            ];
        }
        yield 'currentPlayerId inválido' => [
            $end->with(['currentPlayerId' => 'unknown']),
            gxLegacyProfile(),
            'currentPlayerId é incompatível com turnOrder e com os jogadores.',
            DomainException::class,
        ];
        yield 'perfil inválido' => [
            $end,
            TestFactory::profile(['handLimit' => -1]),
            'RulesProfile inválido.',
            InvalidArgumentException::class,
        ];
        yield 'estado estrutural inválido' => [
            $end->with(['players' => [$end->players[0]]]),
            gxLegacyProfile(),
            'O Duelo deve possuir exatamente dois jogadores.',
            InvalidArgumentException::class,
        ];
    }

    /** @param class-string<\Throwable> $exceptionClass */
    #[DataProvider('invalidStates')]
    public function test_propagates_invalid_state_without_advancing_or_mutating(
        DuelState $duel,
        RulesProfile $profile,
        string $message,
        string $exceptionClass,
    ): void {
        $this->assertFailureIsAtomic($duel, $profile, [], $message, $exceptionClass);
    }

    public function test_function_engine_method_and_manual_composition_are_equivalent(): void
    {
        $duel = $this->withCurrentPlayer(1, range('A', 'H'), ['graveyard' => ['old']]);
        $profile = gxLegacyProfile();
        $selection = ['H', 'B'];

        $fromFunction = processEndPhase($duel, $profile, $selection);
        $fromEngine = Engine::processEndPhase($duel, $profile, $selection);
        $fromManualComposition = startNextTurn(
            discardEndPhaseHandExcess($duel, $profile, $selection),
            $profile,
        );

        self::assertSame($fromManualComposition->toArray(), $fromFunction->toArray());
        self::assertSame($fromManualComposition->toArray(), $fromEngine->toArray());
        self::assertNotSame($fromFunction, $fromEngine);
    }

    public function test_is_deterministic_and_preserves_all_inputs(): void
    {
        $hand = range('A', 'H');
        $graveyard = ['old-1', 'old-2'];
        $selection = ['G', 'C'];
        $handSnapshot = $hand;
        $graveyardSnapshot = $graveyard;
        $selectionSnapshot = $selection;
        $duel = $this->withCurrentPlayer(1, $hand, ['graveyard' => $graveyard]);
        $duelSnapshot = $duel->toArray();

        $first = processEndPhase($duel, gxLegacyProfile(), $selection);
        $second = processEndPhase($duel, gxLegacyProfile(), $selection);

        self::assertSame($duelSnapshot, $duel->toArray());
        self::assertSame($handSnapshot, $hand);
        self::assertSame($graveyardSnapshot, $graveyard);
        self::assertSame($selectionSnapshot, $selection);
        self::assertSame($first->toArray(), $second->toArray());
        self::assertNotSame($first, $second);
        self::assertNotSame($first->players[0], $second->players[0]);
        self::assertNotSame($first->rngState, $second->rngState);
    }

    public function test_result_cannot_be_processed_again_and_draw_phase_was_not_processed(): void
    {
        $duel = $this->withCurrentPlayer(1, range('A', 'G'));
        $result = processEndPhase($duel, gxLegacyProfile(), ['G']);
        $snapshot = $result->toArray();
        $nextPlayer = $result->players[1];

        self::assertSame(DuelPhase::DRAW, $result->phase);
        self::assertSame($duel->players[1]->mainDeck, $nextPlayer->mainDeck);
        self::assertSame($duel->players[1]->hand, $nextPlayer->hand);

        try {
            processEndPhase($result, gxLegacyProfile(), []);
            self::fail('Uma Fase de Compra foi aceita como Fase Final.');
        } catch (DomainException $exception) {
            self::assertSame('O Duelo deve estar na fase END.', $exception->getMessage());
            self::assertSame($snapshot, $result->toArray());
        }
    }

    /**
     * @param  list<string>  $hand
     * @param  array<string, mixed>  $currentPlayerChanges
     */
    private function withCurrentPlayer(int $turnNumber, array $hand, array $currentPlayerChanges = []): DuelState
    {
        $duel = TestFactory::endDuel($turnNumber);
        $players = $duel->players;
        $currentPlayerIndex = $turnNumber % 2 === 1 ? 0 : 1;
        $players[$currentPlayerIndex] = $players[$currentPlayerIndex]->with([
            ...$currentPlayerChanges,
            'hand' => [...$hand],
        ]);

        return $duel->with(['players' => $players]);
    }

    /**
     * @param  list<string>  $selection
     * @param  class-string<\Throwable>  $exceptionClass
     */
    private function assertFailureIsAtomic(
        DuelState $duel,
        RulesProfile $profile,
        array $selection,
        string $message,
        string $exceptionClass,
    ): void {
        $duelSnapshot = $duel->toArray();
        $selectionSnapshot = $selection;
        $currentPlayerId = $duel->currentPlayerId;
        $turnNumber = $duel->turnNumber;
        $phase = $duel->phase;
        $rng = $duel->rngState?->toArray();
        $hands = array_map(static fn ($player): array => $player->hand, $duel->players);
        $graveyards = array_map(static fn ($player): array => $player->graveyard, $duel->players);

        try {
            processEndPhase($duel, $profile, $selection);
            self::fail('Entrada inválida foi aceita.');
        } catch (\Throwable $exception) {
            self::assertInstanceOf($exceptionClass, $exception);
            self::assertSame($message, $exception->getMessage());
            self::assertSame($duelSnapshot, $duel->toArray());
            self::assertSame($selectionSnapshot, $selection);
            self::assertSame($currentPlayerId, $duel->currentPlayerId);
            self::assertSame($turnNumber, $duel->turnNumber);
            self::assertSame($phase, $duel->phase);
            self::assertSame($hands, array_map(static fn ($player): array => $player->hand, $duel->players));
            self::assertSame($graveyards, array_map(static fn ($player): array => $player->graveyard, $duel->players));
            self::assertSame($rng, $duel->rngState?->toArray());
        }
    }
}
