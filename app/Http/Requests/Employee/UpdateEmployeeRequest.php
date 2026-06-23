<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isCompanyAdmin() || $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        $userId = $this->route('employee')->user_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'document_number' => ['required', 'string', 'max:50', Rule::unique('employees')->where('company_id', $this->user()->company_id)->ignore($this->route('employee')->id)],
            'hire_date' => ['nullable', 'date'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'salary_type' => ['nullable', 'in:hourly,monthly'],
            'monthly_base_salary' => ['nullable', 'numeric', 'min:0', 'required_if:salary_type,monthly'],
            'receives_transport_allowance' => ['nullable', 'boolean'],
            'dominical_payment_mode' => ['nullable', 'in:hour,day'],
            'normal_day_value' => ['nullable', 'numeric', 'min:0'],
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            // LOCATIONS FEATURE DISABLED — restore when the feature is re-enabled.
            // 'location_id' => ['nullable', 'exists:locations,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'salary_type.in' => 'El tipo de salario debe ser por hora o mensual.',
            'dominical_payment_mode.in' => 'El modo de pago dominical debe ser por hora o por día.',
            'normal_day_value.numeric' => 'El valor del día normal debe ser numérico.',
            'normal_day_value.min' => 'El valor del día normal no puede ser negativo.',
            'monthly_base_salary.required_if' => 'El salario base mensual es obligatorio para empleados con salario mensual.',
            'monthly_base_salary.numeric' => 'El salario base mensual debe ser un valor numérico.',
            'monthly_base_salary.min' => 'El salario base mensual no puede ser negativo.',
        ];
    }
}
