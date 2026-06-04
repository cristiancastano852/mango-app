<?php

namespace App\Http\Requests\Admin;

use App\Domain\TimeTracking\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBreakRequest extends FormRequest
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
            'break_type_id' => [
                'required',
                $companyId
                    ? Rule::exists('break_types', 'id')->where('company_id', $companyId)
                    : Rule::exists('break_types', 'id'),
            ],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ended_at.after' => __('messages.break_end_after_start'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'started_at' => $this->input('started_at') ? Carbon::parse($this->input('started_at')) : null,
            'ended_at' => $this->input('ended_at') ? Carbon::parse($this->input('ended_at')) : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $timeEntry = $this->route('timeEntry');
            $startedAt = $this->input('started_at');
            $endedAt = $this->input('ended_at');

            if (! $timeEntry instanceof TimeEntry || ! $startedAt instanceof Carbon || ! $endedAt instanceof Carbon) {
                return;
            }

            if (! $timeEntry->clock_out) {
                return;
            }

            if ($startedAt->lt($timeEntry->clock_in) || $endedAt->gt($timeEntry->clock_out)) {
                $validator->errors()->add('started_at', __('messages.break_out_of_range'));
            }
        });
    }
}
