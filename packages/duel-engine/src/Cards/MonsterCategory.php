<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Cards;

enum MonsterCategory: string
{
    case NORMAL = 'NORMAL';
    case EFFECT = 'EFFECT';
    case RITUAL = 'RITUAL';
    case FUSION = 'FUSION';
}
