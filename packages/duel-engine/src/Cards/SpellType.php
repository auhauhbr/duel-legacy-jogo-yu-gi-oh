<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Cards;

enum SpellType: string
{
    case NORMAL = 'NORMAL';
    case CONTINUOUS = 'CONTINUOUS';
    case EQUIP = 'EQUIP';
    case FIELD = 'FIELD';
    case QUICK_PLAY = 'QUICK_PLAY';
    case RITUAL = 'RITUAL';
}
