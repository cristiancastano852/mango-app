<?php

namespace App\Domain\Employee\Actions;

use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeAdjustment;

class SaveEmployeeAdjustment
{
    /**
     * Crea o actualiza un ajuste de nómina del empleado. El `company_id` se hereda del
     * empleado y `created_by` se fija solo al crear (cuando hay usuario autenticado).
     *
     * @param  array{date: string, type: string, amount: numeric, concept: string, note?: ?string}  $data
     */
    public function execute(array $data, Employee $employee, ?EmployeeAdjustment $adjustment = null): EmployeeAdjustment
    {
        $attributes = [
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'date' => $data['date'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'concept' => $data['concept'] ?? null,
            'note' => $data['note'] ?? null,
        ];

        if ($adjustment === null) {
            $attributes['created_by'] = auth()->id();

            return EmployeeAdjustment::create($attributes);
        }

        $adjustment->update($attributes);

        return $adjustment;
    }
}
