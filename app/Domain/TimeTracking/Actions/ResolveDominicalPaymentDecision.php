<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Company\Models\DominicalPaymentDecision;
use Carbon\CarbonInterface;

class ResolveDominicalPaymentDecision
{
    /**
     * Resuelve cuántos dominicales (K) se pagan para un empleado en un periodo, con la
     * precedencia: override explícito del request → decisión guardada del periodo → default
     * (null = pagar todos los N trabajados).
     *
     * Es una decisión por empleado; el reporte de empresa la resuelve empleado por empleado.
     */
    public function execute(
        int $companyId,
        int $employeeId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?int $requestValue = null,
    ): ?int {
        if ($requestValue !== null) {
            return max(0, $requestValue);
        }

        $decision = DominicalPaymentDecision::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('start_date', $startDate->toDateString())
            ->where('end_date', $endDate->toDateString())
            ->first();

        return $decision?->payable_count;
    }
}
