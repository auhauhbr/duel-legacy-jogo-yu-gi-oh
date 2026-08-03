<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Cards;

enum TrapType: string
{
    case NORMAL = 'NORMAL';
    case CONTINUOUS = 'CONTINUOUS';
    case COUNTER = 'COUNTER';
}
