<?php

use App\Http\Middleware\BlockAutomatedSubmissions;
use App\Models\Conference;
use Illuminate\Support\Facades\RateLimiter;

/** Valid form input, as submitted by a person who took a while to type it. */
function visaInput(array $overrides = []): array
{
    return [
        'customer_name' => 'Amina Njeri',
        'passport_number' => 'ak1234567',
        BlockAutomatedSubmissions::TIMESTAMP => BlockAutomatedSubmissions::issueToken(now()->subSeconds(30)),
        ...$overrides,
    ];
}

beforeEach(function () {
    RateLimiter::clear('visa-letter:minute:127.0.0.1');
    RateLimiter::clear('visa-letter:day:127.0.0.1');
});

it('shows the form to guests and asks search engines not to index it', function () {
    Conference::factory()->active()->create(['name' => 'AHAIC 2027']);

    $this->get('/visa-letter')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page
            ->component('VisaLetter')
            ->where('conference.name', 'AHAIC 2027'));
});

it('downloads a letter for a real person', function () {
    Conference::factory()->active()->create();

    $response = $this->post('/visa-letter', visaInput())
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->get('Content-Disposition'))->toContain('AHAIC-Visa-Letter-amina-njeri.pdf');
});

it('blocks a submission that fills the honeypot', function () {
    Conference::factory()->active()->create();

    $this->post('/visa-letter', visaInput([BlockAutomatedSubmissions::HONEYPOT => 'http://spam.example']))
        ->assertSessionHasErrors(BlockAutomatedSubmissions::HONEYPOT);
});

it('blocks a submission made faster than a human could type', function () {
    Conference::factory()->active()->create();

    $this->post('/visa-letter', visaInput([BlockAutomatedSubmissions::TIMESTAMP => BlockAutomatedSubmissions::issueToken()]))
        ->assertSessionHasErrors(BlockAutomatedSubmissions::HONEYPOT);
});

it('limits how many letters one address can request', function () {
    Conference::factory()->active()->create();

    foreach (range(1, 5) as $ignored) {
        $this->post('/visa-letter', visaInput())->assertOk();
    }

    $this->post('/visa-letter', visaInput())->assertSessionHasErrors('throttle');
});

it('counts rejected bot attempts against the limit', function () {
    Conference::factory()->active()->create();

    foreach (range(1, 5) as $ignored) {
        $this->post('/visa-letter', visaInput([BlockAutomatedSubmissions::HONEYPOT => 'x']));
    }

    $this->post('/visa-letter', visaInput())->assertSessionHasErrors('throttle');
});

it('rejects input that is not a name or passport number', function () {
    Conference::factory()->active()->create();

    $this->post('/visa-letter', visaInput([
        'customer_name' => '<script>alert(1)</script>',
        'passport_number' => 'AB 12',
    ]))->assertSessionHasErrors(['customer_name', 'passport_number']);
});

it('refuses letters when no conference is active', function () {
    Conference::factory()->create(['status' => 'draft']);

    $this->post('/visa-letter', visaInput())
        ->assertRedirect()
        ->assertSessionHas('error');
});
