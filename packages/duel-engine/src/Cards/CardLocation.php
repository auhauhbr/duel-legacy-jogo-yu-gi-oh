<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Cards;

enum CardLocation: string
{
    case MAIN_DECK = 'MAIN_DECK';
    case HAND = 'HAND';
    case MONSTER_ZONE = 'MONSTER_ZONE';
    case SPELL_TRAP_ZONE = 'SPELL_TRAP_ZONE';
    case FIELD_ZONE = 'FIELD_ZONE';
    case GRAVEYARD = 'GRAVEYARD';
    case BANISHED_FACE_UP = 'BANISHED_FACE_UP';
    case BANISHED_FACE_DOWN = 'BANISHED_FACE_DOWN';
    case EXTRA_DECK_FACE_DOWN = 'EXTRA_DECK_FACE_DOWN';
    case EXTRA_DECK_FACE_UP = 'EXTRA_DECK_FACE_UP';
}
