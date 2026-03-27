<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEmployeeRequest extends FormRequest
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
        $companyId = $this->user()?->company_id;

        $departmentExists = Rule::exists('departments', 'id');

        if ($companyId) {
            $departmentExists = $departmentExists->where('company_id', $companyId);
        }

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'integer', $departmentExists],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }
}
