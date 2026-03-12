<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBreakTypeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('break_types')
                    ->where('company_id', $companyId)
                    ->ignore($this->route('break_type')),
            ],
            'is_paid' => ['required', 'boolean'],
            'max_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'max_per_day' => ['nullable', 'integer', 'min:1'],
            'is_default' => ['boolean'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
        ];
    }
}
