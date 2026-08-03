<?php

declare(strict_types=1);

namespace DuelLegacy\BotEngine;

/** Fundação futura: bots só poderão escolher entre ações fornecidas pelo motor. */
interface LegalActionChooser
{
    /**
     * @param  non-empty-list<array<string, mixed>>  $legalActions
     * @return array<string, mixed>
     */
    public function choose(array $legalActions): array;
}
