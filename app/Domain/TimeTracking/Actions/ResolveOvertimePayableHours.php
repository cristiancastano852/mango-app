<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Company\Models\OvertimePaymentDecision;
use Carbon\CarbonInterface;

class ResolveOvertimePayableHours
{
    /**
     * Resuelve cuántas horas extra (bolsa única) se pagan para un empleado en un periodo,
     * con la precedencia: override explícito del request → decisión guardada del periodo →
     * default null (pagar todas las horas trabajadas).
     *
     * Solo aplica cuando el overtime está unificado en una sola bolsa diurna (los 3 flags
     * premium de overtime en off). Los valores negativos se normalizan a 0.
     *
     * Es una decisión por empleado; el reporte de empresa la resuelve empleado por empleado.
     */
    public function execute(
        int $companyId,
        int $employeeId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?float $requestValue = null,
    ): ?float {
        if ($requestValue !== null) {
            return max(0.0, $requestValue);
        }

        $decision = OvertimePaymentDecision::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('start_date', $startDate->toDateString())
            ->where('end_date', $endDate->toDateString())
            ->first();

        $saved = $decision?->overtime_payable_hours;

        return $saved !== null ? (float) $saved : null;
    }
}
