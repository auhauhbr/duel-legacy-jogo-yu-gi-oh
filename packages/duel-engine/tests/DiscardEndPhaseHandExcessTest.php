<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DomainException;
use DuelLegacy\DuelEngine\Cards\CardLocation;
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

final class DiscardEndPhaseHandExcessTest extends TestCase
{
    /** @return iterable<string, array{list<string>, list<string>, list<string>, list<string>}> */
    public static function validSelections(): iterable
    {
        yield 'uma carta' => [
            ['A', 'B', 'C', 'D', 'E', 'F', 'G'],
            ['D'],
            ['A', 'B', 'C', 'E', 'F', 'G'],
            ['old', 'D'],
        ];
        yield 'duas cartas em ordem inversa' => [
            ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'],
            ['H', 'B'],
            ['A', 'C', 'D', 'E', 'F', 'G'],
            ['old', 'B', 'H'],
        ];
        yield 'três cartas preservando ordem relativa' => [
            ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
            ['I', 'A', 'E'],
            ['B', 'C', 'D', 'F', 'G', 'H'],
            ['old', 'A', 'E', 'I'],
        ];
    }

    /**
     * @param  list<string>  $hand
     * @param  list<string>  $selection
     * @param  list<string>  $expectedHand
     * @param  list<string>  $expectedGraveyard
     */
    #[DataProvider('validSelections')]
    public function test_discards_selected_cards_in_original_hand_order(
        array $hand,
        array $selection,
        array $expectedHand,
        array $expectedGraveyard,
    ): void {
        $duel = $this->withCurrentPlayer($hand, ['graveyard' => ['old']]);
        $expectedState = $duel->toArray();
        $expectedState['players'][0]['hand'] = $expectedHand;
        $expectedState['players'][0]['graveyard'] = $expectedGraveyard;

        $result = discardEndPhaseHandExcess($duel, gxLegacyProfile(), $selection);

        self::assertSame($expectedState, $result->toArray());
        self::assertSame($expectedHand, TestFactory::ids($result->players[0]->cardZones->hand));
        self::assertSame($expectedGraveyard, TestFactory::ids($result->players[0]->cardZones->graveyard));
        self::assertSame(DuelStatus::ACTIVE, $result->status);
        self::assertSame(DuelPhase::END, $result->phase);
        self::assertSame($duel->currentPlayerId, $result->currentPlayerId);
        self::assertSame($duel->turnNumber, $result->turnNumber);
        self::assertSame($duel->turnOrder, $result->turnOrder);
        self::assertSame($duel->rngState?->toArray(), $result->rngState?->toArray());
    }

    public function test_discards_for_second_physical_player_when_current(): void
    {
        $duel = $this->withCurrentPlayer(['A', 'B', 'C', 'D', 'E', 'F', 'G'], ['graveyard' => ['old-p2']], 2);
        $opponentSnapshot = $duel->players[0]->toArray();
        $expectedState = $duel->toArray();
        $expectedState['players'][1]['hand'] = ['A', 'B', 'D', 'E', 'F', 'G'];
        $expectedState['players'][1]['graveyard'] = ['old-p2', 'C'];

        $result = discardEndPhaseHandExcess($duel, gxLegacyProfile(), ['C']);

        self::assertSame($expectedState, $result->toArray());
        self::assertSame(['A', 'B', 'D', 'E', 'F', 'G'], TestFactory::ids($result->players[1]->cardZones->hand));
        self::assertSame(['old-p2', 'C'], TestFactory::ids($result->players[1]->cardZones->graveyard));
        self::assertSame($opponentSnapshot, $result->players[0]->toArray());
        self::assertSame('player-2', $result->currentPlayerId);
    }

    public function test_respects_custom_hand_limit(): void
    {
        $profile = TestFactory::profile(['startingHandSize' => 7, 'handLimit' => 7]);
        $duel = $this->withCurrentPlayer(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I']);

        $result = discardEndPhaseHandExcess($duel, $profile, ['I', 'B']);

        self::assertSame(['A', 'C', 'D', 'E', 'F', 'G', 'H'], TestFactory::ids($result->players[0]->cardZones->hand));
        self::assertSame(['player-1-graveyard', 'B', 'I'], TestFactory::ids($result->players[0]->cardZones->graveyard));
    }

    public function test_zero_required_discard_returns_independent_equivalent_state(): void
    {
        $duel = $this->withCurrentPlayer(['A', 'B', 'C', 'D', 'E', 'F']);
        $snapshot = $duel->toArray();

        $result = discardEndPhaseHandExcess($duel, gxLegacyProfile(), []);

        self::assertNotSame($duel, $result);
        self::assertSame($snapshot, $result->toArray());
        self::assertNotSame($duel->players[0], $result->players[0]);
        self::assertNotSame($duel->rngState, $result->rngState);
    }

    public function test_treats_similar_looking_ids_as_distinct(): void
    {
        $hand = ['card', ' card', 'card ', 'Card', 'cárd', 'card-1', 'card-01'];
        $result = discardEndPhaseHandExcess($this->withCurrentPlayer($hand), gxLegacyProfile(), [' card']);

        self::assertSame(['card', 'card ', 'Card', 'cárd', 'card-1', 'card-01'], TestFactory::ids($result->players[0]->cardZones->hand));
        self::assertSame(['player-1-graveyard', ' card'], TestFactory::ids($result->players[0]->cardZones->graveyard));
    }

    /** @return iterable<string, array{list<string>, list<string>, string}> */
    public static function invalidQuantities(): iterable
    {
        yield 'vazia com excesso' => [range('A', 'G'), [], 'A quantidade de cartas selecionadas para descarte deve ser exatamente 1; recebida: 0.'];
        yield 'menor que o necessário' => [range('A', 'H'), ['A'], 'A quantidade de cartas selecionadas para descarte deve ser exatamente 2; recebida: 1.'];
        yield 'maior que o necessário' => [range('A', 'G'), ['A', 'B'], 'A quantidade de cartas selecionadas para descarte deve ser exatamente 1; recebida: 2.'];
        yield 'não vazia sem excesso' => [range('A', 'F'), ['A'], 'A quantidade de cartas selecionadas para descarte deve ser exatamente 0; recebida: 1.'];
    }

    /**
     * @param  list<string>  $hand
     * @param  list<string>  $selection
     */
    #[DataProvider('invalidQuantities')]
    public function test_rejects_incorrect_selection_count_without_mutation(array $hand, array $selection, string $message): void
    {
        $duel = $this->withCurrentPlayer($hand);
        $this->assertInvalidSelectionDoesNotMutate($duel, gxLegacyProfile(), $selection, $message);
    }

    /** @return iterable<string, array{string}> */
    public static function blankIds(): iterable
    {
        yield 'string vazia' => [''];
        yield 'espaço ASCII' => [' '];
        yield 'tabulação' => ["\t"];
        yield 'quebra de linha' => ["\n"];
        yield 'NBSP' => ["\u{00A0}"];
        yield 'U+1680' => ["\u{1680}"];
        yield 'U+2000' => ["\u{2000}"];
        yield 'U+200A' => ["\u{200A}"];
        yield 'U+2028' => ["\u{2028}"];
        yield 'U+2029' => ["\u{2029}"];
        yield 'U+202F' => ["\u{202F}"];
        yield 'U+205F' => ["\u{205F}"];
        yield 'U+3000' => ["\u{3000}"];
        yield 'U+FEFF' => ["\u{FEFF}"];
    }

    #[DataProvider('blankIds')]
    public function test_rejects_ecma_script_blank_ids(string $blankId): void
    {
        $duel = $this->withCurrentPlayer(range('A', 'G'));
        $this->assertInvalidSelectionDoesNotMutate(
            $duel,
            gxLegacyProfile(),
            [$blankId],
            'IDs selecionados para descarte não podem ser vazios.',
        );
    }

    public function test_rejects_duplicate_id(): void
    {
        $duel = $this->withCurrentPlayer(range('A', 'H'));
        $this->assertInvalidSelectionDoesNotMutate(
            $duel,
            gxLegacyProfile(),
            ['B', 'B'],
            'IDs selecionados para descarte devem ser únicos.',
        );
    }

    /** @return iterable<string, array{string}> */
    public static function idsOutsideCurrentHand(): iterable
    {
        yield 'inexistente' => ['missing-card'];
        yield 'Deck Principal' => ['player-1-main-1'];
        yield 'Cemitério' => ['player-1-graveyard'];
        yield 'banidas com a face para cima' => ['player-1-banished-up'];
        yield 'banidas com a face para baixo' => ['player-1-banished-down'];
        yield 'Deck Adicional com a face para baixo' => ['player-1-extra-down'];
        yield 'Deck Adicional com a face para cima' => ['player-1-extra-up'];
        yield 'zona' => ['player-1-monster'];
        yield 'adversário' => ['player-2-hand'];
    }

    #[DataProvider('idsOutsideCurrentHand')]
    public function test_rejects_ids_outside_current_players_hand(string $cardInstanceId): void
    {
        $duel = $this->withCurrentPlayer(range('A', 'G'));
        $this->assertInvalidSelectionDoesNotMutate(
            $duel,
            gxLegacyProfile(),
            [$cardInstanceId],
            "A carta selecionada não está na mão do jogador atual: {$cardInstanceId}.",
        );
    }

    /** @return iterable<string, array{DuelState, RulesProfile, string, class-string<\Throwable>}> */
    public static function invalidStates(): iterable
    {
        $active = TestFactory::endDuel();

        yield 'Duel PREPARING' => [
            $active->with(['status' => DuelStatus::PREPARING]),
            gxLegacyProfile(),
            'Somente um Duelo ACTIVE pode descartar o excesso da mão na Fase Final.',
            DomainException::class,
        ];
        yield 'Duel FINISHED' => [
            $active->with(['status' => DuelStatus::FINISHED]),
            gxLegacyProfile(),
            'Somente um Duelo ACTIVE pode descartar o excesso da mão na Fase Final.',
            DomainException::class,
        ];
        yield 'fase diferente de END' => [
            $active->with(['phase' => DuelPhase::MAIN_1]),
            gxLegacyProfile(),
            'O Duelo deve estar na fase END.',
            DomainException::class,
        ];
        yield 'currentPlayerId inválido' => [
            $active->with(['currentPlayerId' => 'unknown']),
            gxLegacyProfile(),
            'currentPlayerId é incompatível com turnOrder e com os jogadores.',
            DomainException::class,
        ];
        yield 'estado estrutural inválido' => [
            $active->with(['players' => [$active->players[0]]]),
            gxLegacyProfile(),
            'O Duelo deve possuir exatamente dois jogadores.',
            InvalidArgumentException::class,
        ];
        yield 'perfil inválido' => [
            $active,
            TestFactory::profile(['handLimit' => -1]),
            'RulesProfile inválido.',
            InvalidArgumentException::class,
        ];
    }

    /** @param class-string<\Throwable> $exceptionClass */
    #[DataProvider('invalidStates')]
    public function test_rejects_invalid_state_or_profile_without_mutation(
        DuelState $duel,
        RulesProfile $profile,
        string $message,
        string $exceptionClass,
    ): void {
        $snapshot = $duel->toArray();

        try {
            discardEndPhaseHandExcess($duel, $profile, []);
            self::fail('Estado inválido foi aceito.');
        } catch (\Throwable $exception) {
            self::assertInstanceOf($exceptionClass, $exception);
            self::assertSame($message, $exception->getMessage());
            self::assertSame($snapshot, $duel->toArray());
        }
    }

    public function test_preserves_inputs_and_returns_independent_deterministic_states(): void
    {
        $hand = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $graveyard = ['old-1', 'old-2'];
        $selection = ['H', 'B'];
        $handSnapshot = $hand;
        $graveyardSnapshot = $graveyard;
        $selectionSnapshot = $selection;
        $duel = $this->withCurrentPlayer($hand, ['graveyard' => $graveyard]);
        $duelSnapshot = $duel->toArray();
        $opponentSnapshot = $duel->players[1]->toArray();

        $first = discardEndPhaseHandExcess($duel, gxLegacyProfile(), $selection);
        $second = discardEndPhaseHandExcess($duel, gxLegacyProfile(), $selection);

        self::assertSame($duelSnapshot, $duel->toArray());
        self::assertSame($handSnapshot, $hand);
        self::assertSame($graveyardSnapshot, $graveyard);
        self::assertSame($selectionSnapshot, $selection);
        self::assertNotSame($duel, $first);
        self::assertNotSame($duel->players[0], $first->players[0]);
        self::assertSame($opponentSnapshot, $first->players[1]->toArray());
        self::assertSame($duel->rngState?->toArray(), $first->rngState?->toArray());
        self::assertSame($first->toArray(), $second->toArray());
        self::assertNotSame($first->players[0], $second->players[0]);
        self::assertNotSame($first->rngState, $second->rngState);

        $serializedResult = $first->toArray();
        $serializedResult['players'][0]['hand'][] = 'mutated-copy';
        self::assertSame($duelSnapshot, $duel->toArray());
        self::assertSame(['A', 'C', 'D', 'E', 'F', 'G'], TestFactory::ids($first->players[0]->cardZones->hand));
    }

    public function test_function_and_engine_method_are_equivalent(): void
    {
        $duel = $this->withCurrentPlayer(range('A', 'G'));

        $fromFunction = discardEndPhaseHandExcess($duel, gxLegacyProfile(), ['G']);
        $fromEngine = Engine::discardEndPhaseHandExcess($duel, gxLegacyProfile(), ['G']);

        self::assertSame($fromFunction->toArray(), $fromEngine->toArray());
        self::assertNotSame($fromFunction, $fromEngine);
    }

    /**
     * @param  list<string>  $hand
     * @param  array<string, mixed>  $currentPlayerChanges
     */
    private function withCurrentPlayer(array $hand, array $currentPlayerChanges = [], int $turnNumber = 1): DuelState
    {
        $duel = TestFactory::endDuel($turnNumber);
        $players = $duel->players;
        $currentPlayerIndex = $turnNumber % 2 === 1 ? 0 : 1;
        $player = TestFactory::withZoneIds($players[$currentPlayerIndex], CardLocation::HAND, $hand);
        if (isset($currentPlayerChanges['graveyard']) && is_array($currentPlayerChanges['graveyard'])) {
            $graveyard = $currentPlayerChanges['graveyard'];
            if (! array_is_list($graveyard) || array_filter($graveyard, static fn (mixed $id): bool => ! is_string($id)) !== []) {
                throw new \LogicException('Fixture de Cemitério inválida.');
            }
            $player = TestFactory::withZoneIds($player, CardLocation::GRAVEYARD, $graveyard);
            unset($currentPlayerChanges['graveyard']);
        }
        $players[$currentPlayerIndex] = $player->with($currentPlayerChanges);

        return $duel->with(['players' => $players]);
    }

    /** @param list<string> $selection */
    private function assertInvalidSelectionDoesNotMutate(
        DuelState $duel,
        RulesProfile $profile,
        array $selection,
        string $message,
    ): void {
        $duelSnapshot = $duel->toArray();
        $selectionSnapshot = $selection;

        try {
            discardEndPhaseHandExcess($duel, $profile, $selection);
            self::fail('Seleção inválida foi aceita.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertSame($duelSnapshot, $duel->toArray());
            self::assertSame($selectionSnapshot, $selection);
        }
    }
}
