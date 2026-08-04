<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Duels\DuelState;
use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\createDeterministicRng;
use function DuelLegacy\DuelEngine\createInitialDuelState;
use function DuelLegacy\DuelEngine\createInitialPlayerState;
use function DuelLegacy\DuelEngine\getLegalMainPhaseOneTransitions;
use function DuelLegacy\DuelEngine\getRequiredEndPhaseDiscardCount;
use function DuelLegacy\DuelEngine\gxLegacyProfile;
use function DuelLegacy\DuelEngine\nextRandomFloat;
use function DuelLegacy\DuelEngine\nextRandomInt;
use function DuelLegacy\DuelEngine\nextRandomUint32;
use function DuelLegacy\DuelEngine\prepareInitialDuelState;
use function DuelLegacy\DuelEngine\processDrawPhase;
use function DuelLegacy\DuelEngine\processStandbyPhase;
use function DuelLegacy\DuelEngine\shuffleDeterministically;
use function DuelLegacy\DuelEngine\startFirstTurn;
use function DuelLegacy\DuelEngine\startNextTurn;
use function DuelLegacy\DuelEngine\transitionFromMainPhaseOne;
use function DuelLegacy\DuelEngine\validateRulesProfile;

final class ReferenceParityTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $fixture;

    protected function setUp(): void
    {
        $contents = file_get_contents(__DIR__.'/Fixtures/typescript-reference.json');
        self::assertNotFalse($contents);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $this->fixture = $decoded;
    }

    public function test_rng_matches_every_type_script_golden_vector_bit_for_bit(): void
    {
        foreach ($this->fixture['rng'] as $vector) {
            $initial = createDeterministicRng($vector['seed']);
            self::assertSame($vector['initialState'], $initial->toArray(), "Estado inicial de {$vector['seed']}");

            $state = $initial;
            $values = [];
            for ($index = 0; $index < 8; $index++) {
                $result = nextRandomUint32($state);
                $values[] = $result->value;
                $state = $result->nextState;
            }
            self::assertSame($vector['uint32'], $values, "uint32 de {$vector['seed']}");
            self::assertSame($vector['uint32FinalState'], $state->toArray());

            $state = $initial;
            $values = [];
            for ($index = 0; $index < 5; $index++) {
                $result = nextRandomFloat($state);
                $values[] = $result->value;
                $state = $result->nextState;
            }
            self::assertSame($vector['floats'], $values, "floats de {$vector['seed']}");
            self::assertSame($vector['floatFinalState'], $state->toArray());

            $state = $initial;
            foreach ($vector['integers'] as $integer) {
                $result = nextRandomInt($state, $integer['minInclusive'], $integer['maxExclusive']);
                self::assertSame($integer['value'], $result->value, "inteiro de {$vector['seed']}");
                $state = $result->nextState;
            }
            self::assertSame($vector['intFinalState'], $state->toArray());

            $shuffle = shuffleDeterministically(range('A', 'J'), $initial);
            self::assertSame($vector['shuffle'], $shuffle->toArray(), "shuffle de {$vector['seed']}");
        }
    }

    public function test_invalid_seed_behavior_matches_type_script(): void
    {
        foreach ($this->fixture['invalidSeeds'] as $vector) {
            self::assertFalse($vector['accepted']);
            try {
                createDeterministicRng($vector['seed']);
                self::fail('A seed inválida foi aceita.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame($vector['message'], $exception->getMessage());
            }
        }
    }

    public function test_ecma_script_trim_fixture_matches_every_historical_validation_site(): void
    {
        foreach ($this->fixture['trimValidation'] as $vector) {
            $value = $vector['value'];
            self::assertIsString($value);
            self::assertIsBool($vector['trimmedEmpty']);

            if ($vector['trimmedEmpty']) {
                self::assertFalse(validateRulesProfile(TestFactory::profile(['id' => $value]))->valid);
                $this->assertInvalidArgumentMessage(
                    static fn (): mixed => createDeterministicRng($value),
                    'A seed não pode ser vazia.',
                );
                $this->assertInvalidArgumentMessage(
                    static fn (): mixed => prepareInitialDuelState(TestFactory::initialDuel(), gxLegacyProfile(), $value),
                    'A seed não pode ser vazia.',
                );
                $this->assertInvalidArgumentMessage(
                    static fn (): mixed => createInitialPlayerState(TestFactory::profile(), $value, [], []),
                    'playerId não pode ser vazio.',
                );
                $this->assertInvalidArgumentMessage(
                    static fn (): mixed => TestFactory::card($value),
                    'O identificador não pode ser vazio.',
                );
                $players = [TestFactory::player('p1'), TestFactory::player('p2')];
                foreach ([0, 1, 2] as $metadataIndex) {
                    $metadata = ['duel', 'engine', 'pool'];
                    $metadata[$metadataIndex] = $value;
                    $messages = ['duelId não pode ser vazio.', 'engineVersion não pode ser vazia.', 'cardPoolVersion não pode ser vazia.'];
                    $this->assertInvalidArgumentMessage(
                        static fn (): mixed => createInitialDuelState($metadata[0], TestFactory::profile(), $metadata[1], $metadata[2], $players, 'p1'),
                        $messages[$metadataIndex],
                    );
                }

                continue;
            }

            $rng = createDeterministicRng($value);
            self::assertSame($value, $rng->seed);
            self::assertSame(
                $value,
                prepareInitialDuelState(TestFactory::initialDuel(), gxLegacyProfile(), $value)->rngState?->seed,
            );
            $profile = TestFactory::profile(['id' => $value]);
            self::assertTrue(validateRulesProfile($profile)->valid);
            self::assertSame($value, $profile->id);
            $player = createInitialPlayerState($profile, $value, TestFactory::cards(["card-{$value}"]), []);
            self::assertSame($value, $player->playerId);
            self::assertSame("card-{$value}", $player->cardZones->mainDeck->cards()[0]->id->value);
            $other = createInitialPlayerState($profile, 'other', TestFactory::cards(['other-card']), []);
            $duel = createInitialDuelState($value, $profile, $value, $value, [$player, $other], $value);
            self::assertSame([$value, $value, $value], [$duel->duelId, $duel->engineVersion, $duel->cardPoolVersion]);
        }
    }

    public function test_complete_structural_flow_matches_type_script_fixture(): void
    {
        $profile = gxLegacyProfile();
        $player = static fn (string $id) => createInitialPlayerState(
            $profile,
            $id,
            TestFactory::cards(array_map(static fn (int $index): string => "{$id}-main-{$index}", range(1, 40))),
            TestFactory::cards(["{$id}-extra-1", "{$id}-extra-2"]),
        );
        $states = [];
        $states['initial'] = createInitialDuelState('duel-reference', $profile, 'engine-reference', 'pool-reference', [$player('player-1'), $player('player-2')], 'player-2');
        $states['prepared'] = prepareInitialDuelState($states['initial'], $profile, 'paridade GX 🔥');
        $states['firstTurn'] = startFirstTurn($states['prepared'], $profile);
        $states['afterFirstDraw'] = processDrawPhase($states['firstTurn'], $profile);
        $states['firstMain'] = processStandbyPhase($states['afterFirstDraw'], $profile);
        $states['firstLegalTransitions'] = array_map(static fn (DuelPhase $phase): string => $phase->value, getLegalMainPhaseOneTransitions($states['firstMain'], $profile));
        $states['firstEnd'] = transitionFromMainPhaseOne($states['firstMain'], $profile, DuelPhase::END);
        $states['firstDiscardCount'] = getRequiredEndPhaseDiscardCount($states['firstEnd'], $profile);
        $states['secondTurn'] = startNextTurn($states['firstEnd'], $profile);
        $states['afterSecondDraw'] = processDrawPhase($states['secondTurn'], $profile);
        $states['secondMain'] = processStandbyPhase($states['afterSecondDraw'], $profile);
        $states['secondLegalTransitions'] = array_map(static fn (DuelPhase $phase): string => $phase->value, getLegalMainPhaseOneTransitions($states['secondMain'], $profile));

        foreach ($states as $name => $state) {
            $actual = $state instanceof DuelState ? $state->toArray() : $state;
            self::assertSame($this->fixture['flow'][$name], $actual, "Fluxo divergente em {$name}");
        }
    }

    /** @param \Closure(): mixed $operation */
    private function assertInvalidArgumentMessage(\Closure $operation, string $message): void
    {
        try {
            $operation();
            self::fail('A entrada composta apenas por whitespace foi aceita.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
        }
    }
}
