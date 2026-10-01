<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class CommitmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_address' => ['required', 'string'],
            'billing_city' => ['required', 'string', 'max:255'],
            'billing_country' => ['required', 'string', 'max:255'],
            'billing_postal_code' => ['nullable', 'string', 'max:50'],
            'tax_details' => ['nullable', 'string'],
        ];
    }
}
