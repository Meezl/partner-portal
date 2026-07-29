<?php

namespace App\Http\Requests\Partner;

use App\Enums\SessionFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'format' => ['required', 'string', new Enum(SessionFormat::class)],
            'organizers' => ['nullable', 'array'],
            'co_hosts' => ['nullable', 'array'],
            'target_audience' => ['nullable', 'string'],
            'expected_participants' => ['nullable', 'integer', 'min:1'],
            'is_open' => ['boolean'],
            'special_requirements' => ['nullable', 'array'],
        ];
    }
}
