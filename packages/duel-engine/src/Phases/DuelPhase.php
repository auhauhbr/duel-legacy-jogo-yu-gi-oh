<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Phases;

enum DuelPhase: string
{
    case DRAW = 'DRAW';
    case STANDBY = 'STANDBY';
    case MAIN_1 = 'MAIN_1';
    case BATTLE = 'BATTLE';
    case MAIN_2 = 'MAIN_2';
    case END = 'END';
}
