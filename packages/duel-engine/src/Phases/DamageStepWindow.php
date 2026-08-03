<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Phases;

enum DamageStepWindow: string
{
    case START = 'START';
    case BEFORE_DAMAGE_CALCULATION = 'BEFORE_DAMAGE_CALCULATION';
    case DAMAGE_CALCULATION = 'DAMAGE_CALCULATION';
    case AFTER_DAMAGE_CALCULATION = 'AFTER_DAMAGE_CALCULATION';
    case END = 'END';
}
