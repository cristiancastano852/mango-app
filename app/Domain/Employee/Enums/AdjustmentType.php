<?php

namespace App\Domain\Employee\Enums;

enum AdjustmentType: string
{
    case Bonus = 'Bonus';
    case Deduction = 'Deduction';

    /**
     * Signo del ajuste sobre el neto a pagar: +1 suma, -1 resta.
     */
    public function sign(): int
    {
        return match ($this) {
            self::Bonus => 1,
            self::Deduction => -1,
        };
    }
}
