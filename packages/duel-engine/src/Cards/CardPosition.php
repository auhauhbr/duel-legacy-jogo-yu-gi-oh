<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Cards;

enum CardPosition: string
{
    case FACE_UP_ATTACK = 'FACE_UP_ATTACK';
    case FACE_UP_DEFENSE = 'FACE_UP_DEFENSE';
    case FACE_DOWN_DEFENSE = 'FACE_DOWN_DEFENSE';
}
