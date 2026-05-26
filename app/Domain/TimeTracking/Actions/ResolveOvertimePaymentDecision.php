<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Company\Models\OvertimePaymentDecision;
use App\Domain\Company\Models\SurchargeRule;
use Carbon\CarbonInterface;

class ResolveOvertimePaymentDecision
{
    /**
     * Resuelve si las horas extra se pagan para un reporte, con la precedencia:
     * override explícito del request → decisión guardada del periodo → default de la compañía.
     *
     * $employeeId nulo representa la decisión del reporte de empresa.
     */
    public function execute(
        int $companyId,
        ?int $employeeId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?bool $requestValue = null,
    ): bool {
        if ($requestValue !== null) {
            return $requestValue;
        }

        $decision = OvertimePaymentDecision::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('start_date', $startDate->toDateString())
            ->where('end_date', $endDate->toDateString())
            ->when(
                $employeeId === null,
                fn ($query) => $query->whereNull('employee_id'),
                fn ($query) => $query->where('employee_id', $employeeId),
            )
            ->first();

        if ($decision !== null) {
            return $decision->pay_overtime;
        }

        return (bool) SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->value('pay_overtime_by_default');
    }
}
