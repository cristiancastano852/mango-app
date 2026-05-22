<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
