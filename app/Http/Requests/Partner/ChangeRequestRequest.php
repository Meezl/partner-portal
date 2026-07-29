<?php

namespace App\Http\Requests\Partner;

use App\Enums\ChangeRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conference_session_id' => ['required', 'exists:conference_sessions,id'],
            'type' => ['required', 'string', new Enum(ChangeRequestType::class)],
            'requested_value' => ['required', 'string'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
