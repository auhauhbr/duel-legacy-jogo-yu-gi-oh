<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Tests;

use DuelLegacy\DuelEngine\Cards\CardKind;
use DuelLegacy\DuelEngine\Cards\TrapCardDefinition;
use DuelLegacy\DuelEngine\Cards\TrapType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrapCardDefinitionTest extends TestCase
{
    /** @return iterable<string, array{TrapType}> */
    public static function trapTypes(): iterable
    {
        foreach (TrapType::cases() as $type) {
            yield $type->value => [$type];
        }
    }

    #[DataProvider('trapTypes')]
    public function test_constructs_every_trap_type_and_reports_trap_kind(TrapType $type): void
    {
        $definition = new TrapCardDefinition('trap-1', 'Armadilha fictícia', '', $type);

        self::assertSame($type, $definition->trapType);
        self::assertSame(CardKind::TRAP, $definition->kind);
        self::assertSame([
            'id' => 'trap-1',
            'name' => 'Armadilha fictícia',
            'text' => '',
            'kind' => 'TRAP',
            'trapType' => $type->value,
        ], $definition->toArray());
    }

    public function test_preserves_filled_text_and_rejects_invalid_common_fields(): void
    {
        $definition = new TrapCardDefinition(' trap-1 ', ' Armadilha fictícia ', ' Texto fictício. ', TrapType::CONTINUOUS);
        self::assertSame([' trap-1 ', ' Armadilha fictícia ', ' Texto fictício. '], [$definition->id, $definition->name, $definition->text]);

        foreach ([['', 'Nome', 'id não pode ser vazio.'], ['id', '', 'name não pode ser vazio.']] as [$id, $name, $message]) {
            try {
                new TrapCardDefinition($id, $name, '', TrapType::NORMAL);
                self::fail('Campo comum inválido foi aceito.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function test_is_immutable(): void
    {
        $definition = new TrapCardDefinition('trap-1', 'Armadilha fictícia', '', TrapType::COUNTER);
        self::assertTrue((new \ReflectionClass($definition))->isReadOnly());

        try {
            $property = new \ReflectionProperty($definition, 'trapType');
            $property->setValue($definition, TrapType::NORMAL);
            self::fail('Definição readonly foi alterada.');
        } catch (\Error) {
            self::assertSame(TrapType::COUNTER, $definition->trapType);
        }
    }
}
