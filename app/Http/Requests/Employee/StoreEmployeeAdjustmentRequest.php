<?php

namespace App\Http\Requests\Employee;

use App\Domain\Employee\Enums\AdjustmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isCompanyAdmin() || $this->user()->isSuperAdmin();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'type' => ['required', Rule::enum(AdjustmentType::class)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'concept' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'La fecha del ajuste es obligatoria.',
            'date.date' => 'La fecha del ajuste no es válida.',
            'type.required' => 'El tipo de ajuste es obligatorio.',
            'type.enum' => 'El tipo de ajuste debe ser bonificación o deducción.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser un valor numérico.',
            'amount.gt' => 'El monto debe ser mayor que cero.',
        ];
    }
}
