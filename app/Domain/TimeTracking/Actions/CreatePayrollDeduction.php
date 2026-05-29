<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\PayrollDeduction;

class CreatePayrollDeduction
{
    /**
     * Registra un descuento por novedad contra un empleado.
     *
     * El `company_id` se toma del empleado (no del usuario) para que funcione también con
     * super-admin, que no tiene company propia.
     *
     * @param  array{employee_id: int, effective_date: string, days: float|string, reason: string, notes?: ?string}  $data
     */
    public function execute(array $data, ?int $createdBy): PayrollDeduction
    {
        $employee = Employee::withoutGlobalScopes()->findOrFail($data['employee_id']);

        return PayrollDeduction::withoutGlobalScopes()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'effective_date' => $data['effective_date'],
            'days' => $data['days'],
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $createdBy,
        ]);
    }
}
