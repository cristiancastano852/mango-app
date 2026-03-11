<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurchargeRuleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userCompanyId = $this->user()->company_id;

        $rules = [
            'night_surcharge' => ['required', 'numeric', 'min:0', 'max:999'],
            'overtime_day' => ['required', 'numeric', 'min:0', 'max:999'],
            'overtime_night' => ['required', 'numeric', 'min:0', 'max:999'],
            'sunday_holiday' => ['required', 'numeric', 'min:0', 'max:999'],
            'overtime_day_sunday' => ['required', 'numeric', 'min:0', 'max:999'],
            'overtime_night_sunday' => ['required', 'numeric', 'min:0', 'max:999'],
            'night_sunday' => ['required', 'numeric', 'min:0', 'max:999'],
            'max_weekly_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'night_start_time' => ['required', 'date_format:H:i'],
            'night_end_time' => ['required', 'date_format:H:i'],
        ];

        // Admin can only target their own company; super-admin (company_id = null) has no restriction.
        if ($userCompanyId !== null) {
            $rules['company_id'] = ['sometimes', 'integer', Rule::in([$userCompanyId])];
        } else {
            $rules['company_id'] = ['sometimes', 'integer', Rule::exists('surcharge_rules', 'company_id')];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'night_start_time.required' => 'El inicio del horario nocturno es obligatorio.',
            'night_start_time.date_format' => 'El inicio del horario nocturno debe tener formato HH:MM (ej: 21:00).',
            'night_end_time.required' => 'El fin del horario nocturno es obligatorio.',
            'night_end_time.date_format' => 'El fin del horario nocturno debe tener formato HH:MM (ej: 06:00).',
            'company_id.in' => 'No puedes modificar la configuración de otra empresa.',
        ];
    }
}
