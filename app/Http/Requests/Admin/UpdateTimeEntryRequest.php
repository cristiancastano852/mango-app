<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isCompanyAdmin() || $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'clock_in' => ['required', 'date'],
            'clock_out' => ['required', 'date', 'after:clock_in'],
            'edit_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'clock_in' => $this->input('clock_in') ? \Carbon\Carbon::parse($this->input('clock_in')) : null,
            'clock_out' => $this->input('clock_out') ? \Carbon\Carbon::parse($this->input('clock_out')) : null,
        ]);
    }
}
