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
            'max_weekly_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'max_daily_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'night_start_time' => ['required', 'date_format:H:i'],
            'night_end_time' => ['required', 'date_format:H:i'],
            'default_monthly_salary' => ['required', 'numeric', 'min:0'],
            'default_hourly_rate' => ['required', 'numeric', 'min:0'],
            'transport_allowance' => ['required', 'numeric', 'min:0'],
            'dominical_weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'pay_dominical_by_default' => ['required', 'boolean'],
            'default_dominical_payment_mode' => ['required', Rule::in(['hour', 'day'])],
            'default_normal_day_value' => ['required', 'numeric', 'min:0'],
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
            'max_daily_minutes.required' => 'El límite diario de horas ordinarias es obligatorio.',
            'max_daily_minutes.integer' => 'El límite diario debe ser un número entero de minutos.',
            'max_daily_minutes.min' => 'El límite diario debe ser al menos 1 minuto.',
            'max_daily_minutes.max' => 'El límite diario no puede superar 24 horas (1440 minutos).',
            'max_weekly_minutes.required' => 'El límite semanal de horas ordinarias es obligatorio.',
            'max_weekly_minutes.integer' => 'El límite semanal debe ser un número entero de minutos.',
            'max_weekly_minutes.min' => 'El límite semanal debe ser al menos 1 minuto.',
            'max_weekly_minutes.max' => 'El límite semanal no puede superar 168 horas (10080 minutos).',
            'pay_overtime_by_default.required' => 'Debes indicar si las horas extra se pagan por defecto.',
            'pay_overtime_by_default.boolean' => 'El valor de pago de horas extra no es válido.',
            'default_monthly_salary.required' => 'El salario base mensual por defecto es obligatorio.',
            'default_monthly_salary.numeric' => 'El salario base mensual por defecto debe ser numérico.',
            'default_hourly_rate.required' => 'El valor hora por defecto es obligatorio.',
            'default_hourly_rate.numeric' => 'El valor hora por defecto debe ser numérico.',
            'transport_allowance.required' => 'El auxilio de transporte es obligatorio.',
            'transport_allowance.numeric' => 'El auxilio de transporte debe ser numérico.',
            'transport_allowance.min' => 'El auxilio de transporte no puede ser negativo.',
            'dominical_weekday.required' => 'Debes indicar qué día es el dominical.',
            'dominical_weekday.min' => 'El día dominical no es válido.',
            'dominical_weekday.max' => 'El día dominical no es válido.',
            'pay_dominical_by_default.required' => 'Debes indicar si se pagan los dominicales por defecto.',
            'pay_dominical_by_default.boolean' => 'El valor de pago de dominicales no es válido.',
            'default_dominical_payment_mode.required' => 'Debes indicar el modo de pago dominical.',
            'default_dominical_payment_mode.in' => 'El modo de pago dominical debe ser por hora o por día.',
            'default_normal_day_value.required' => 'El valor del día normal es obligatorio.',
            'default_normal_day_value.numeric' => 'El valor del día normal debe ser numérico.',
            'default_normal_day_value.min' => 'El valor del día normal no puede ser negativo.',
            'company_id.in' => 'No puedes modificar la configuración de otra empresa.',
        ];
    }
}
