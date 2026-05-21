<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = $this->route('company')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('companies', 'slug')->ignore($companyId)],
            'timezone' => ['required', 'timezone:all'],
            'country' => ['nullable', 'string', 'size:2'],
            'subscription_plan' => ['nullable', 'string', 'max:100'],
            'trial_ends_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => __('messages.slug_invalid_format'),
            'slug.unique' => __('messages.slug_already_taken'),
        ];
    }
}
