<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2024'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'venue' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'registration_deadline' => ['nullable', 'date'],
            'onboarding_deadline' => ['nullable', 'date'],
            'lock_date' => ['nullable', 'date'],
        ];
    }
}
