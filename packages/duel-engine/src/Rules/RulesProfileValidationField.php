<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Rules;

enum RulesProfileValidationField: string
{
    case ID = 'id';
    case STARTING_LIFE_POINTS = 'startingLifePoints';
    case STARTING_HAND_SIZE = 'startingHandSize';
    case HAND_LIMIT = 'handLimit';
    case MAIN_DECK_MIN = 'mainDeckMin';
    case MAIN_DECK_MAX = 'mainDeckMax';
    case EXTRA_DECK_MAX = 'extraDeckMax';
    case SIDE_DECK_MAX = 'sideDeckMax';
    case MAIN_MONSTER_ZONES = 'mainMonsterZones';
    case SPELL_TRAP_ZONES = 'spellTrapZones';
    case ENABLED_SUMMONS = 'enabledSummons';
}
