<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Phases;

final class PhaseOrder
{
    /** @return list<DuelPhase> */
    public static function standard(): array
    {
        return [DuelPhase::DRAW, DuelPhase::STANDBY, DuelPhase::MAIN_1, DuelPhase::BATTLE, DuelPhase::MAIN_2, DuelPhase::END];
    }

    /** @return list<BattleStep> */
    public static function battleSteps(): array
    {
        return [BattleStep::START, BattleStep::BATTLE, BattleStep::DAMAGE, BattleStep::END];
    }

    /** @return list<DamageStepWindow> */
    public static function damageStepWindows(): array
    {
        return [
            DamageStepWindow::START,
            DamageStepWindow::BEFORE_DAMAGE_CALCULATION,
            DamageStepWindow::DAMAGE_CALCULATION,
            DamageStepWindow::AFTER_DAMAGE_CALCULATION,
            DamageStepWindow::END,
        ];
    }
}
