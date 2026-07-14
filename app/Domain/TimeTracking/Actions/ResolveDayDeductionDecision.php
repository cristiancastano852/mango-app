<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Company\Models\DayDeductionDecision;
use Carbon\CarbonInterface;

class ResolveDayDeductionDecision
{
    /**
     * Resuelve cuántos días se descuentan del total a pagar para un empleado en un periodo,
     * con la precedencia: override explícito del request → decisión guardada del periodo →
     * default 0 (no descontar).
     *
     * Los valores negativos se normalizan a 0. Es una decisión por empleado y periodo.
     */
    public function execute(
        int $companyId,
        int $employeeId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?int $requestValue = null,
    ): int {
        if ($requestValue !== null) {
            return max(0, $requestValue);
        }

        $decision = DayDeductionDecision::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('start_date', $startDate->toDateString())
            ->where('end_date', $endDate->toDateString())
            ->first();

        return (int) ($decision?->deducted_days ?? 0);
    }
}
