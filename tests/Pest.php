<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A 1x1 PNG data URL, standing in for a signature drawn in the portal's
 * signature pad. Real signatures are larger but identical in shape.
 */
function drawnSignature(): string
{
    return 'data:image/png;base64,'.base64_encode(base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));
}

/**
 * Everything the signing form sends: the signatory, their drawn signature, and
 * the witness who countersigns.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function signingPayload(array $overrides = []): array
{
    return array_merge([
        'signer_name' => 'Jane Partner',
        'signer_title' => 'Executive Director',
        'signature_image' => drawnSignature(),
        'witness_name' => 'Ken Witness',
        'witness_title' => 'Finance Manager',
        'witness_signature_image' => drawnSignature(),
        'accept_terms' => true,
    ], $overrides);
}
