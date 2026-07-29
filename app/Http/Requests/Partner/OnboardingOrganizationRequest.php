<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => ['nullable', 'file', 'image', 'max:5120'],
            'description' => ['nullable', 'string', 'max:500'],
            'social_media' => ['nullable', 'array'],
            'social_media.*' => ['nullable', 'string', 'url'],
            'number_of_participants' => ['nullable', 'integer', 'min:1'],
            'exhibition_preferences' => ['nullable', 'string'],
        ];
    }
}
