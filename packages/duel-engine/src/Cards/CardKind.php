<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Cards;

enum CardKind: string
{
    case MONSTER = 'MONSTER';
    case SPELL = 'SPELL';
    case TRAP = 'TRAP';
}
