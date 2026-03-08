<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isCompanyAdmin() || $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'break_duration' => ['required', 'integer', 'min:0'],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['required', 'integer', 'between:0,6'],
        ];
    }
}
