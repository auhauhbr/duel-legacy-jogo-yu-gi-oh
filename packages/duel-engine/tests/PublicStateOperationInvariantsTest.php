<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardLocation;
use DuelLegacy\DuelEngine\Duels\DuelResultReason;
use DuelLegacy\DuelEngine\Duels\DuelState;
use DuelLegacy\DuelEngine\Duels\DuelStatus;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Rules\RulesProfile;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use DuelLegacy\DuelEngine\Zones\MonsterZones;
use DuelLegacy\DuelEngine\Zones\SpellTrapZones;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\discardEndPhaseHandExcess;
use function DuelLegacy\DuelEngine\getLegalMainPhaseOneTransitions;
use function DuelLegacy\DuelEngine\getRequiredEndPhaseDiscardCount;
use function DuelLegacy\DuelEngine\gxLegacyProfile;
use function DuelLegacy\DuelEngine\processDrawPhase;
use function DuelLegacy\DuelEngine\processEndPhase;
use function DuelLegacy\DuelEngine\processStandbyPhase;
use function DuelLegacy\DuelEngine\startFirstTurn;
use function DuelLegacy\DuelEngine\startNextTurn;
use function DuelLegacy\DuelEngine\transitionFromMainPhaseOne;

final class PublicStateOperationInvariantsTest extends TestCase
{
    /**
     * @return array<string, array{phase: DuelPhase, status: string, wrongPhase: string, current: string}>
     */
    private static function activeOperations(): array
    {
        return [
            'processDrawPhase' => [
                'phase' => DuelPhase::DRAW,
                'status' => 'Somente um Duelo ACTIVE pode processar a Fase de Compra.',
                'wrongPhase' => 'O Duelo deve estar na fase DRAW.',
                'current' => 'A Fase de Compra deve possuir jogador atual.',
            ],
            'processStandbyPhase' => [
                'phase' => DuelPhase::STANDBY,
                'status' => 'Somente um Duelo ACTIVE pode processar a Fase de Apoio.',
                'wrongPhase' => 'O Duelo deve estar na fase STANDBY.',
                'current' => 'A Fase de Apoio deve possuir jogador atual.',
            ],
            'getLegalMainPhaseOneTransitions' => [
                'phase' => DuelPhase::MAIN_1,
                'status' => 'Somente um Duelo ACTIVE pode processar a Fase Principal 1.',
                'wrongPhase' => 'O Duelo deve estar na fase MAIN_1.',
                'current' => 'A Fase Principal 1 deve possuir jogador atual.',
            ],
            'transitionFromMainPhaseOne' => [
                'phase' => DuelPhase::MAIN_1,
                'status' => 'Somente um Duelo ACTIVE pode processar a Fase Principal 1.',
                'wrongPhase' => 'O Duelo deve estar na fase MAIN_1.',
                'current' => 'A Fase Principal 1 deve possuir jogador atual.',
            ],
            'startNextTurn' => [
                'phase' => DuelPhase::END,
                'status' => 'Somente um Duelo ACTIVE pode iniciar o próximo turno.',
                'wrongPhase' => 'O Duelo deve estar na fase END.',
                'current' => 'A Fase Final deve possuir jogador atual.',
            ],
            'getRequiredEndPhaseDiscardCount' => [
                'phase' => DuelPhase::END,
                'status' => 'Somente um Duelo ACTIVE pode consultar o descarte.',
                'wrongPhase' => 'O Duelo deve estar na fase END.',
                'current' => 'A Fase Final deve possuir jogador atual.',
            ],
            'discardEndPhaseHandExcess' => [
                'phase' => DuelPhase::END,
                'status' => 'Somente um Duelo ACTIVE pode descartar o excesso da mão na Fase Final.',
                'wrongPhase' => 'O Duelo deve estar na fase END.',
                'current' => 'A Fase Final deve possuir jogador atual.',
            ],
            'processEndPhase' => [
                'phase' => DuelPhase::END,
                'status' => 'Somente um Duelo ACTIVE pode descartar o excesso da mão na Fase Final.',
                'wrongPhase' => 'O Duelo deve estar na fase END.',
                'current' => 'A Fase Final deve possuir jogador atual.',
            ],
        ];
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidActiveStates(): iterable
    {
        $common = [
            'turn zero' => ['turn_zero', 'turnNumber deve ser um inteiro maior ou igual a 1.'],
            'turn negative' => ['turn_negative', 'turnNumber deve ser um inteiro maior ou igual a 1.'],
            'turn decimal' => ['turn_decimal', 'turnNumber deve ser um inteiro maior ou igual a 1.'],
            'current null' => ['current_null', 'CURRENT_MESSAGE'],
            'current unknown' => ['current_unknown', 'currentPlayerId é incompatível com turnOrder e com os jogadores.'],
            'current incompatible' => ['current_incompatible', 'currentPlayerId é incompatível com turnOrder e com os jogadores.'],
            'winner' => ['winner', 'Um Duelo ACTIVE não pode possuir vencedor.'],
            'result reason' => ['result_reason', 'Um Duelo ACTIVE não pode possuir motivo de resultado.'],
            'rng absent' => ['rng_absent', 'O Duelo deve possuir um estado de RNG.'],
            'invalid profile' => ['profile_invalid', 'RulesProfile inválido.'],
            'incompatible profile' => ['profile_incompatible', 'RulesProfile incompatível com o Duelo.'],
            'one player' => ['one_player', 'O Duelo deve possuir exatamente dois jogadores.'],
            'three players' => ['three_players', 'O Duelo deve possuir exatamente dois jogadores.'],
            'duplicate player ids' => ['duplicate_player_ids', 'Os jogadores devem possuir IDs diferentes.'],
            'duplicate turn order' => ['duplicate_turn_order', 'turnOrder deve conter exatamente dois jogadores.'],
            'unknown turn order' => ['unknown_turn_order', 'turnOrder é incompatível com os jogadores do Duelo.'],
            'monster zones' => ['monster_zones', 'As zonas do jogador são incompatíveis com o perfil.'],
            'spell/trap zones' => ['spell_trap_zones', 'As zonas do jogador são incompatíveis com o perfil.'],
        ];
        $cardAreas = [
            'mainDeck', 'hand', 'graveyard', 'banishedFaceUp', 'banishedFaceDown',
            'extraDeckFaceDown', 'extraDeckFaceUp', 'monsterZones', 'spellTrapZones',
            'fieldZone',
        ];

        foreach (self::activeOperations() as $operation => $messages) {
            yield "{$operation}: status" => [$operation, 'status', $messages['status']];
            yield "{$operation}: phase" => [$operation, 'phase', $messages['wrongPhase']];
            foreach ($common as $name => [$mutation, $message]) {
                yield "{$operation}: {$name}" => [
                    $operation,
                    $mutation,
                    $message === 'CURRENT_MESSAGE' ? $messages['current'] : $message,
                ];
            }
            foreach ($cardAreas as $area) {
                yield "{$operation}: duplicate {$area}" => [
                    $operation,
                    "duplicate_card:{$area}",
                    'IDs de instância de carta devem ser únicos no Duelo.',
                ];
            }
        }
    }

    #[DataProvider('invalidActiveStates')]
    public function test_every_active_operation_rejects_corrupt_state_without_mutation(
        string $operation,
        string $mutation,
        string $message,
    ): void {
        $configuration = self::activeOperations()[$operation];
        $state = TestFactory::activeDuel($configuration['phase'], 2);
        [$invalidState, $profile] = $this->mutate($state, gxLegacyProfile(), $mutation);
        $snapshot = $invalidState->toArray();

        try {
            $this->execute($operation, $invalidState, $profile);
            self::fail("{$operation} aceitou {$mutation}.");
        } catch (\Throwable $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertSame($snapshot, $invalidState->toArray());
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidPreparedStates(): iterable
    {
        $cases = [
            'status' => ['status', 'Somente um Duelo em PREPARING pode ser iniciado.'],
            'rng absent' => ['rng_absent', 'O Duelo deve possuir um estado de RNG.'],
            'turn number' => ['prepared_turn', 'O Duelo preparado deve estar antes do primeiro turno.'],
            'current unknown' => ['current_unknown', 'O Duelo preparado não pode possuir jogador atual.'],
            'current incompatible' => ['current_incompatible', 'O Duelo preparado não pode possuir jogador atual.'],
            'phase' => ['prepared_phase', 'O Duelo preparado não pode possuir fase atual.'],
            'winner' => ['winner', 'O Duelo preparado não pode possuir vencedor.'],
            'result reason' => ['result_reason', 'O Duelo preparado não pode possuir motivo de resultado.'],
            'invalid profile' => ['profile_invalid', 'RulesProfile inválido.'],
            'incompatible profile' => ['profile_incompatible', 'RulesProfile incompatível com o Duelo.'],
            'one player' => ['one_player', 'O Duelo deve possuir exatamente dois jogadores.'],
            'three players' => ['three_players', 'O Duelo deve possuir exatamente dois jogadores.'],
            'duplicate player ids' => ['duplicate_player_ids', 'Os jogadores devem possuir IDs diferentes.'],
            'duplicate turn order' => ['duplicate_turn_order', 'turnOrder deve conter exatamente dois jogadores.'],
            'unknown turn order' => ['unknown_turn_order', 'turnOrder é incompatível com os jogadores do Duelo.'],
            'monster zones' => ['monster_zones', 'As zonas do jogador são incompatíveis com o perfil.'],
            'spell/trap zones' => ['spell_trap_zones', 'As zonas do jogador são incompatíveis com o perfil.'],
            'initial hand' => ['initial_hand', 'A mão inicial é incompatível com o perfil.'],
        ];
        foreach ($cases as $name => $case) {
            yield $name => $case;
        }
        foreach (['mainDeck', 'hand', 'graveyard', 'banishedFaceUp', 'banishedFaceDown', 'extraDeckFaceDown', 'extraDeckFaceUp', 'monsterZones', 'spellTrapZones', 'fieldZone'] as $area) {
            yield "duplicate {$area}" => ["duplicate_card:{$area}", 'IDs de instância de carta devem ser únicos no Duelo.'];
        }
    }

    #[DataProvider('invalidPreparedStates')]
    public function test_start_first_turn_rejects_corrupt_state_without_mutation(string $mutation, string $message): void
    {
        [$state, $profile] = $this->mutate(TestFactory::preparedDuel(), gxLegacyProfile(), $mutation);
        $snapshot = $state->toArray();
        try {
            startFirstTurn($state, $profile);
            self::fail("startFirstTurn aceitou {$mutation}.");
        } catch (\Throwable $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertSame($snapshot, $state->toArray());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function operations(): iterable
    {
        yield 'startFirstTurn' => ['startFirstTurn'];
        foreach (array_keys(self::activeOperations()) as $operation) {
            yield $operation => [$operation];
        }
    }

    #[DataProvider('operations')]
    public function test_successful_calls_preserve_input_and_return_independent_domain_states(string $operation): void
    {
        $state = $operation === 'startFirstTurn'
            ? TestFactory::preparedDuel()
            : TestFactory::activeDuel(self::activeOperations()[$operation]['phase'], 2);
        $snapshot = $state->toArray();
        $first = $this->execute($operation, $state, gxLegacyProfile());
        $second = $this->execute($operation, $state, gxLegacyProfile());

        self::assertSame($snapshot, $state->toArray());
        if ($first instanceof DuelState && $second instanceof DuelState) {
            self::assertNotSame($state, $first);
            self::assertNotSame($first, $second);
            self::assertSame($first->toArray(), $second->toArray());
            self::assertNotSame($first->players[0], $second->players[0]);
            self::assertNotSame($first->rngState, $second->rngState);
            foreach ([0, 1] as $playerIndex) {
                self::assertSame($state->players[$playerIndex]->monsterZones, $first->players[$playerIndex]->monsterZones);
                self::assertSame($state->players[$playerIndex]->monsterZones, $second->players[$playerIndex]->monsterZones);
                self::assertSame($state->players[$playerIndex]->spellTrapZones, $first->players[$playerIndex]->spellTrapZones);
                self::assertSame($state->players[$playerIndex]->spellTrapZones, $second->players[$playerIndex]->spellTrapZones);
                foreach ($state->players[$playerIndex]->monsterZones->slots() as $slotIndex => $card) {
                    self::assertSame($card, $first->players[$playerIndex]->monsterZones->get($slotIndex));
                    self::assertSame($card?->definition, $first->players[$playerIndex]->monsterZones->get($slotIndex)?->definition);
                }
                foreach ($state->players[$playerIndex]->spellTrapZones->slots() as $slotIndex => $card) {
                    self::assertSame($card, $first->players[$playerIndex]->spellTrapZones->get($slotIndex));
                    self::assertSame($card?->definition, $first->players[$playerIndex]->spellTrapZones->get($slotIndex)?->definition);
                }
            }
        } else {
            self::assertSame($first, $second);
        }

        $serialized = $state->toArray();
        $serialized['players'][0]['mainDeck'][] = 'mutated-copy';
        self::assertSame($snapshot, $state->toArray());
    }

    /** @return array{DuelState, RulesProfile} */
    private function mutate(DuelState $state, RulesProfile $profile, string $mutation): array
    {
        if ($mutation === 'profile_invalid') {
            return [$state, TestFactory::profile(['startingLifePoints' => 0])];
        }
        if ($mutation === 'profile_incompatible') {
            return [$state, TestFactory::profile(['id' => 'OTHER'])];
        }
        if (str_starts_with($mutation, 'duplicate_card:')) {
            return [$this->withDuplicateCard($state, substr($mutation, strlen('duplicate_card:'))), $profile];
        }

        $players = $state->players;
        $changes = match ($mutation) {
            'status' => ['status' => $state->status === DuelStatus::ACTIVE ? DuelStatus::PREPARING : DuelStatus::ACTIVE],
            'phase' => ['phase' => $state->phase === DuelPhase::DRAW ? DuelPhase::STANDBY : DuelPhase::DRAW],
            'prepared_phase' => ['phase' => DuelPhase::DRAW],
            'turn_zero' => ['turnNumber' => 0],
            'turn_negative' => ['turnNumber' => -1],
            'turn_decimal' => ['turnNumber' => 1.5],
            'prepared_turn' => ['turnNumber' => 1],
            'current_null' => ['currentPlayerId' => null],
            'current_unknown' => ['currentPlayerId' => 'unknown'],
            'current_incompatible' => ['currentPlayerId' => $state->currentPlayerId === 'player-1' ? 'player-2' : 'player-1'],
            'winner' => ['winnerId' => 'player-1'],
            'result_reason' => ['resultReason' => DuelResultReason::DECK_OUT],
            'rng_absent' => ['rngState' => null],
            'one_player' => ['players' => [$players[0]]],
            'three_players' => ['players' => [...$players, TestFactory::player('player-3')]],
            'duplicate_player_ids' => ['players' => [$players[0], $players[1]->with(['playerId' => $players[0]->playerId])]],
            'duplicate_turn_order' => ['turnOrder' => ['player-1', 'player-1']],
            'unknown_turn_order' => ['turnOrder' => ['player-1', 'unknown']],
            'monster_zones' => ['players' => [$players[0]->with(['monsterZones' => MonsterZones::empty(0)]), $players[1]]],
            'spell_trap_zones' => ['players' => [$players[0], $players[1]->with(['spellTrapZones' => SpellTrapZones::empty(0)])]],
            'initial_hand' => ['players' => [TestFactory::withZoneIds($players[0], CardLocation::HAND, []), $players[1]]],
            default => throw new \LogicException("Mutação desconhecida: {$mutation}"),
        };

        return [$state->with($changes), $profile];
    }

    private function withDuplicateCard(DuelState $state, string $area): DuelState
    {
        $players = $state->players;
        $duplicate = $players[0]->cardZones->mainDeck->cards()[0]->id->value;
        $location = match ($area) {
            'mainDeck' => CardLocation::MAIN_DECK,
            'hand' => CardLocation::HAND,
            'graveyard' => CardLocation::GRAVEYARD,
            'banishedFaceUp' => CardLocation::BANISHED_FACE_UP,
            'banishedFaceDown' => CardLocation::BANISHED_FACE_DOWN,
            'extraDeckFaceDown' => CardLocation::EXTRA_DECK_FACE_DOWN,
            'extraDeckFaceUp' => CardLocation::EXTRA_DECK_FACE_UP,
            default => null,
        };
        if ($location !== null) {
            $players[1] = TestFactory::withZoneIds($players[1], $location, [$duplicate]);
        } else {
            $change = match ($area) {
                'monsterZones' => ['monsterZones' => TestFactory::monsterZones([TestFactory::card($duplicate), null, null, null, null])],
                'spellTrapZones' => ['spellTrapZones' => TestFactory::spellTrapZones([TestFactory::card($duplicate), null, null, null, null])],
                'fieldZone' => ['fieldZone' => $duplicate],
                default => throw new \LogicException("Área desconhecida: {$area}"),
            };
            $players[1] = $players[1]->with($change);
        }

        return $state->with(['players' => $players]);
    }

    private function execute(string $operation, DuelState $state, RulesProfile $profile): mixed
    {
        return match ($operation) {
            'startFirstTurn' => startFirstTurn($state, $profile),
            'processDrawPhase' => processDrawPhase($state, $profile),
            'processStandbyPhase' => processStandbyPhase($state, $profile),
            'getLegalMainPhaseOneTransitions' => getLegalMainPhaseOneTransitions($state, $profile),
            'transitionFromMainPhaseOne' => transitionFromMainPhaseOne($state, $profile, DuelPhase::END),
            'startNextTurn' => startNextTurn($state, $profile),
            'getRequiredEndPhaseDiscardCount' => getRequiredEndPhaseDiscardCount($state, $profile),
            'discardEndPhaseHandExcess' => discardEndPhaseHandExcess($state, $profile, []),
            'processEndPhase' => processEndPhase($state, $profile, []),
            default => throw new \LogicException("Operação desconhecida: {$operation}"),
        };
    }
}
