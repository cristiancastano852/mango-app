<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSurchargeRuleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'night_surcharge' => ['required', 'numeric', 'min:0', 'max:999'],
            'overtime_day' => ['required', 'numeric', 'min:0', 'max:999'],
            'overtime_night' => ['required', 'numeric', 'min:0', 'max:999'],
            'sunday_holiday' => ['required', 'numeric', 'min:0', 'max:999'],
            'overtime_day_sunday' => ['required', 'numeric', 'min:0', 'max:999'],
            'overtime_night_sunday' => ['required', 'numeric', 'min:0', 'max:999'],
            'night_sunday' => ['required', 'numeric', 'min:0', 'max:999'],
            'max_weekly_hours' => ['required', 'integer', 'min:1', 'max:168'],
        ];
    }
}
