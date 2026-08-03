<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Duels;

enum DuelStatus: string
{
    case PREPARING = 'PREPARING';
    case ACTIVE = 'ACTIVE';
    case FINISHED = 'FINISHED';
}
