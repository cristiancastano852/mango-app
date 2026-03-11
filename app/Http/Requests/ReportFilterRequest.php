<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $isEmployeeReport = $this->routeIs('reports.employee', 'reports.employee.*');

        return [
            'date_range' => ['required', 'in:day,week,biweekly,month,custom'],
            'start_date' => ['required_if:date_range,custom', 'nullable', 'date'],
            'end_date' => ['required_if:date_range,custom', 'nullable', 'date', 'after_or_equal:start_date'],
            'employee_id' => $isEmployeeReport
                ? ['required', 'integer', 'exists:employees,id']
                : ['nullable', 'integer', 'exists:employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_range.required' => 'El rango de fechas es obligatorio.',
            'date_range.in' => 'El rango de fechas seleccionado no es válido.',
            'start_date.required_if' => 'La fecha de inicio es obligatoria para rangos personalizados.',
            'end_date.required_if' => 'La fecha de fin es obligatoria para rangos personalizados.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'employee_id.exists' => 'El empleado seleccionado no existe.',
            'department_id.exists' => 'El departamento seleccionado no existe.',
        ];
    }
}
