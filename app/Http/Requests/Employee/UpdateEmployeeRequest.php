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
            'employee_code' => ['nullable', 'string', 'max:50'],
            'hire_date' => ['nullable', 'date'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'salary_type' => ['nullable', 'in:hourly,monthly'],
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
        ];
    }
}
