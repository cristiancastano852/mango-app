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
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            // LOCATIONS FEATURE DISABLED — restore when the feature is re-enabled.
            // 'location_id' => ['nullable', 'exists:locations,id'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
        ];
    }
}
