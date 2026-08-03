<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Rules;

final class RulesProfiles
{
    public const string GX_LEGACY = 'GX_LEGACY';

    public static function gxLegacy(): RulesProfile
    {
        return new RulesProfile(
            id: self::GX_LEGACY,
            startingLifePoints: 8000,
            startingHandSize: 5,
            handLimit: 6,
            mainDeckMin: 40,
            mainDeckMax: 60,
            extraDeckMax: 15,
            sideDeckMax: 0,
            mainMonsterZones: 5,
            spellTrapZones: 5,
            hasFieldZone: true,
            hasExtraMonsterZones: false,
            hasPendulumZones: false,
            hasSkillCard: false,
            drawOnFirstTurn: false,
            battleOnFirstTurn: false,
            enabledSummons: [
                SummonMethod::NORMAL,
                SummonMethod::TRIBUTE,
                SummonMethod::SET,
                SummonMethod::FLIP,
                SummonMethod::SPECIAL_BY_EFFECT,
                SummonMethod::RITUAL,
                SummonMethod::FUSION,
            ],
        );
    }
}
