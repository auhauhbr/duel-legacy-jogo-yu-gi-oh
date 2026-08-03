<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Rules;

enum SummonMethod: string
{
    case NORMAL = 'NORMAL';
    case TRIBUTE = 'TRIBUTE';
    case SET = 'SET';
    case FLIP = 'FLIP';
    case SPECIAL_BY_EFFECT = 'SPECIAL_BY_EFFECT';
    case RITUAL = 'RITUAL';
    case FUSION = 'FUSION';
}
