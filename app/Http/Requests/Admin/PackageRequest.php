<?php

namespace App\Http\Requests\Admin;

use App\Enums\PackageTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class PackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tier' => ['required', 'string', new Enum(PackageTier::class)],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['string', 'max:10'],
            'description' => ['nullable', 'string'],
            'benefits' => ['nullable', 'array'],
            'session_slots' => ['integer', 'min:1'],
            'exhibition_space' => ['nullable', 'string'],
            'max_partners' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
