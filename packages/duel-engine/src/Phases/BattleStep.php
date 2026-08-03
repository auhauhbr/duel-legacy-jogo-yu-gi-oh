<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Phases;

enum BattleStep: string
{
    case START = 'START';
    case BATTLE = 'BATTLE';
    case DAMAGE = 'DAMAGE';
    case END = 'END';
}
