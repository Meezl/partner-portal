<?php

use App\Http\Middleware\BlockAutomatedSubmissions;
use App\Models\User;

const HONEYPOT = BlockAutomatedSubmissions::HONEYPOT;
const TIMESTAMP = BlockAutomatedSubmissions::TIMESTAMP;

/** A timestamp far enough in the past to look like a human filling a form. */
function humanTimestamp(): string
{
    return (string) ((now()->timestamp - 30) * 1000);
}

it('lets a real person log in', function () {
    $user = User::factory()->create(['email' => 'real@example.com']);

    $this->post('/login', [
        'email' => 'real@example.com',
        'password' => 'password',
        TIMESTAMP => humanTimestamp(),
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

it('blocks a login that fills the honeypot', function () {
    User::factory()->create(['email' => 'real@example.com']);

    $this->post('/login', [
        'email' => 'real@example.com',
        'password' => 'password',
        HONEYPOT => 'http://spam.example',
        TIMESTAMP => humanTimestamp(),
    ])->assertSessionHasErrors(HONEYPOT);

    $this->assertGuest();
});

it('blocks a login submitted faster than a human could type', function () {
    User::factory()->create(['email' => 'real@example.com']);

    $this->post('/login', [
        'email' => 'real@example.com',
        'password' => 'password',
        TIMESTAMP => (string) (now()->timestamp * 1000),
    ])->assertSessionHasErrors(HONEYPOT);

    $this->assertGuest();
});

it('blocks a registration that fills the honeypot', function () {
    $this->post('/register', [
        'name' => 'Spam Bot',
        'email' => 'bot@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        HONEYPOT => 'anything',
        TIMESTAMP => humanTimestamp(),
    ])->assertSessionHasErrors(HONEYPOT);

    expect(User::where('email', 'bot@example.com')->exists())->toBeFalse();
});

it('blocks an instant registration', function () {
    $this->post('/register', [
        'name' => 'Fast Bot',
        'email' => 'fast@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        TIMESTAMP => (string) (now()->timestamp * 1000),
    ])->assertSessionHasErrors(HONEYPOT);

    expect(User::where('email', 'fast@example.com')->exists())->toBeFalse();
});

it('still allows a genuine registration', function () {
    $this->post('/register', [
        'name' => 'Real Person',
        'email' => 'person@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        TIMESTAMP => humanTimestamp(),
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'person@example.com')->exists())->toBeTrue();
});

it('does not lock out a client that sends no timestamp at all', function () {
    // A cached page or a non-JS client has no timestamp; refusing those would
    // block real people, so the honeypot alone must carry that case.
    $user = User::factory()->create(['email' => 'nojs@example.com']);

    $this->post('/login', [
        'email' => 'nojs@example.com',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

it('rate limits repeated registration attempts from one address', function () {
    // Four attempts against a limit of three per minute.
    for ($i = 1; $i <= 4; $i++) {
        $response = $this->post('/register', [
            'name' => "Bot {$i}",
            'email' => "bot{$i}@example.com",
            'password' => 'password123',
            'password_confirmation' => 'password123',
            TIMESTAMP => humanTimestamp(),
        ]);
    }

    expect($response->status())->toBe(429);
    expect(User::where('email', 'bot4@example.com')->exists())->toBeFalse();
});
