<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isCompanyAdmin() || $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'document_number' => ['required', 'string', 'max:50', Rule::unique('employees')->where('company_id', $this->user()->company_id)],
            'hire_date' => ['nullable', 'date'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'salary_type' => ['nullable', 'in:hourly,monthly'],
            'monthly_base_salary' => ['nullable', 'numeric', 'min:0', 'required_if:salary_type,monthly'],
            'receives_transport_allowance' => ['nullable', 'boolean'],
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            // LOCATIONS FEATURE DISABLED — restore when the feature is re-enabled.
            // 'location_id' => ['nullable', 'exists:locations,id'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'salary_type.in' => 'El tipo de salario debe ser por hora o mensual.',
            'monthly_base_salary.required_if' => 'El salario base mensual es obligatorio para empleados con salario mensual.',
            'monthly_base_salary.numeric' => 'El salario base mensual debe ser un valor numérico.',
            'monthly_base_salary.min' => 'El salario base mensual no puede ser negativo.',
        ];
    }
}
