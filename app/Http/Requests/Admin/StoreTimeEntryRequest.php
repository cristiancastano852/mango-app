<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isCompanyAdmin() || $this->user()->isSuperAdmin();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'employee_id' => [
                'required',
                $companyId
                    ? Rule::exists('employees', 'id')->where('company_id', $companyId)
                    : Rule::exists('employees', 'id'),
            ],
            'date' => ['required', 'date'],
            'clock_in' => ['required', 'date'],
            'clock_out' => ['required', 'date', 'after:clock_in'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clock_out.after' => __('messages.clock_out_after_clock_in'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'clock_in' => $this->input('clock_in') ? Carbon::parse($this->input('clock_in')) : null,
            'clock_out' => $this->input('clock_out') ? Carbon::parse($this->input('clock_out')) : null,
        ]);
    }

    /**
     * Garantiza un único registro activo (no eliminado) por empleado y día.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $employeeId = $this->input('employee_id');
            $date = $this->input('date');

            if (! $employeeId || ! $date) {
                return;
            }

            $exists = \App\Domain\TimeTracking\Models\TimeEntry::query()
                ->where('employee_id', $employeeId)
                ->whereDate('date', $date)
                ->exists();

            if ($exists) {
                $validator->errors()->add('employee_id', __('messages.time_entry_already_exists'));
            }
        });
    }
}
