<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A signature drawn in the browser, arriving as a PNG data URL.
 *
 * The value is embedded straight into the agreement PDF, so it is checked
 * rather than trusted: only a base64 PNG is allowed, and it is capped so a
 * signature box cannot be used to push megabytes into the database.
 */
class SignatureImage implements ValidationRule
{
    /** Roughly 1 MB of base64, far more than a drawn signature needs. */
    private const MAX_LENGTH = 1_400_000;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a drawn signature.');

            return;
        }

        if (strlen($value) > self::MAX_LENGTH) {
            $fail('The :attribute is too large. Please redraw it.');

            return;
        }

        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#', $value, $matches)) {
            $fail('The :attribute must be a signature drawn in the signature box.');

            return;
        }

        $decoded = base64_decode($matches[1], true);

        // The PNG magic number, so a base64 payload of something else is caught.
        if ($decoded === false || ! str_starts_with($decoded, "\x89PNG\r\n\x1a\n")) {
            $fail('The :attribute could not be read as a signature. Please redraw it.');
        }
    }
}
