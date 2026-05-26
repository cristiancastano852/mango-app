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
            'pay_overtime_by_default' => ['required', 'boolean'],
            'max_weekly_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'max_daily_hours' => ['required', 'integer', 'min:1', 'max:24'],
            'night_start_time' => ['required', 'date_format:H:i'],
            'night_end_time' => ['required', 'date_format:H:i'],
        ];

        // Admin can only target their own company; super-admin must explicitly supply which company.
        if ($userCompanyId !== null) {
            $rules['company_id'] = ['sometimes', 'integer', Rule::in([$userCompanyId])];
        } else {
            $rules['company_id'] = ['required', 'integer', Rule::exists('surcharge_rules', 'company_id')];
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
            'max_daily_hours.required' => 'El límite diario de horas ordinarias es obligatorio.',
            'max_daily_hours.integer' => 'El límite diario debe ser un número entero.',
            'max_daily_hours.min' => 'El límite diario debe ser al menos 1 hora.',
            'max_daily_hours.max' => 'El límite diario no puede superar 24 horas.',
            'pay_overtime_by_default.required' => 'Debes indicar si las horas extra se pagan por defecto.',
            'pay_overtime_by_default.boolean' => 'El valor de pago de horas extra no es válido.',
            'company_id.in' => 'No puedes modificar la configuración de otra empresa.',
        ];
    }
}
