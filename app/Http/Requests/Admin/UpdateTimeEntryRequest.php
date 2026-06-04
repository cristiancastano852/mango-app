<?php

namespace App\Http\Requests\Admin;

use App\Domain\TimeTracking\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isCompanyAdmin() || $this->user()->isSuperAdmin();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'clock_in' => ['required', 'date'],
            'clock_out' => ['required', 'date', 'after:clock_in'],
            'edit_reason' => ['required', 'string', 'max:1000'],
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
     * Las pausas existentes deben seguir dentro del nuevo rango del turno.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $clockIn = $this->input('clock_in');
            $clockOut = $this->input('clock_out');

            if (! $clockIn instanceof Carbon || ! $clockOut instanceof Carbon) {
                return;
            }

            $timeEntry = $this->route('timeEntry');

            if (! $timeEntry instanceof TimeEntry) {
                return;
            }

            $hasBreakOutOfRange = $timeEntry->breaks()
                ->where(function ($query) use ($clockIn, $clockOut) {
                    $query->where('started_at', '<', $clockIn)
                        ->orWhere('ended_at', '>', $clockOut);
                })
                ->exists();

            if ($hasBreakOutOfRange) {
                $validator->errors()->add('clock_in', __('messages.break_out_of_range'));
            }
        });
    }
}
