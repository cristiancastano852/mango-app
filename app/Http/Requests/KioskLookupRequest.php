<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KioskLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_number' => ['required', 'string', 'max:50'],
        ];
    }
}
