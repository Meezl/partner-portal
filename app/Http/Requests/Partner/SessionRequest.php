<?php

namespace App\Http\Requests\Partner;

use App\Enums\ParticipantRange;
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
            'co_hosts' => ['nullable', 'array'],
            'expected_participants' => ['nullable', new Enum(ParticipantRange::class)],
            'special_requirements' => ['nullable', 'array'],
        ];
    }
}
