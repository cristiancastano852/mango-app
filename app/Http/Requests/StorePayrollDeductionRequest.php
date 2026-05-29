<?php

namespace App\Http\Requests;

use App\Domain\TimeTracking\Enums\PayrollDeductionReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollDeductionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where(function ($query) use ($companyId) {
                    $query->where('salary_type', 'monthly');

                    if ($companyId !== null) {
                        $query->where('company_id', $companyId);
                    }
                }),
            ],
            'effective_date' => ['required', 'date'],
            'days' => ['required', 'numeric', 'min:0.5', 'max:31'],
            'reason' => ['required', Rule::enum(PayrollDeductionReason::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.exists' => __('messages.payroll_deduction_employee_invalid'),
            'days.min' => __('messages.payroll_deduction_days_min'),
        ];
    }
}
