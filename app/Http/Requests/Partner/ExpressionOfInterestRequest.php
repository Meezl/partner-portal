<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class ExpressionOfInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_title' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'physical_address' => ['nullable', 'string', 'max:1000'],
            'physical_city' => ['nullable', 'string', 'max:255'],
            'physical_country' => ['nullable', 'string', 'max:255'],
            'physical_postal_code' => ['nullable', 'string', 'max:50'],
            'sponsorship_package_id' => ['required', 'exists:sponsorship_packages,id'],
        ];
    }
}
