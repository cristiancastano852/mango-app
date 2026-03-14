<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanySettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        $scheduleRule = ['nullable', 'integer'];

        if ($companyId) {
            $scheduleRule[] = Rule::exists('schedules', 'id')
                ->where('company_id', $companyId);
        } else {
            $scheduleRule[] = Rule::exists('schedules', 'id');
        }

        return [
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:0,6'],
            'default_schedule_id' => $scheduleRule,
        ];
    }
}
