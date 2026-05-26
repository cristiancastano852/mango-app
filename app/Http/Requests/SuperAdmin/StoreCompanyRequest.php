<?php

namespace App\Http\Requests\SuperAdmin;

use App\Domain\Shared\Tenancy\Tenancy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'subdomain' => [
                'nullable',
                'string',
                'max:63',
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn(Tenancy::reservedSubdomains()),
                'unique:companies,slug',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'El nombre de la empresa es obligatorio.',
            'company_name.max' => 'El nombre de la empresa no puede tener más de 255 caracteres.',
            'admin_name.required' => 'El nombre del administrador es obligatorio.',
            'admin_name.max' => 'El nombre del administrador no puede tener más de 255 caracteres.',
            'admin_email.required' => 'El correo electrónico es obligatorio.',
            'admin_email.email' => 'Ingresa un correo electrónico válido.',
            'admin_email.unique' => 'Este correo electrónico ya está registrado.',
            'subdomain.regex' => 'El subdominio solo puede contener letras minúsculas, números y guiones.',
            'subdomain.max' => 'El subdominio no puede tener más de 63 caracteres.',
            'subdomain.not_in' => 'Ese subdominio está reservado.',
            'subdomain.unique' => 'Ese subdominio ya está en uso.',
        ];
    }
}
