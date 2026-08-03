<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Tests\Support\TestFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DuelLegacy\DuelEngine\createInitialPlayerState;
use function DuelLegacy\DuelEngine\gxLegacyProfile;

final class CreateInitialPlayerStateTest extends TestCase
{
    public function test_creates_complete_initial_state(): void
    {
        $main = ['main-1', 'main-2'];
        $extra = ['extra-1'];
        $state = createInitialPlayerState(gxLegacyProfile(), 'player-1', $main, $extra);
        self::assertSame('player-1', $state->playerId);
        self::assertSame(8000, $state->lifePoints);
        self::assertSame($main, $state->mainDeck);
        self::assertSame($extra, $state->extraDeckFaceDown);
        self::assertSame([], $state->hand);
        self::assertSame([], $state->graveyard);
        self::assertSame([null, null, null, null, null], $state->monsterZones);
        self::assertSame([null, null, null, null, null], $state->spellTrapZones);
        self::assertNull($state->fieldZone);
        self::assertSame(0, $state->normalSummonsUsed);
        self::assertSame(1, $state->normalSummonLimit);
    }

    /** @return iterable<array{string, list<string>, list<string>, string}> */
    public static function invalidInputs(): iterable
    {
        yield [' ', [], [], 'playerId não pode ser vazio.'];
        yield ['p1', [''], [], 'IDs de instância de carta não podem ser vazios.'];
        yield ['p1', ['x', 'x'], [], 'IDs de instância de carta devem ser únicos.'];
        yield ['p1', ['x'], ['x'], 'IDs de instância de carta devem ser únicos.'];
    }

    /**
     * @param  list<string>  $main
     * @param  list<string>  $extra
     */
    #[DataProvider('invalidInputs')]
    public function test_rejects_invalid_inputs(string $playerId, array $main, array $extra, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        createInitialPlayerState(gxLegacyProfile(), $playerId, $main, $extra);
    }

    public function test_rejects_oversized_extra_deck_and_invalid_profile(): void
    {
        try {
            createInitialPlayerState(
                gxLegacyProfile(),
                'p1',
                [],
                array_map(static fn (int $index): string => "extra-{$index}", range(1, 16)),
            );
            self::fail();
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Deck Adicional excede o limite do perfil.', $exception->getMessage());
        }
        $this->expectExceptionMessage('RulesProfile inválido.');
        createInitialPlayerState(TestFactory::profile(['startingLifePoints' => 0]), 'p1', [], []);
    }

    public function test_readonly_state_cannot_be_mutated(): void
    {
        $state = TestFactory::player('p1');
        $snapshot = $state->toArray();
        try {
            $property = new \ReflectionProperty($state, 'mainDeck');
            $property->setValue($state, ['illegal']);
            self::fail('Readonly array was mutated.');
        } catch (\Error) {
            self::assertSame($snapshot, $state->toArray());
        }
    }
}
