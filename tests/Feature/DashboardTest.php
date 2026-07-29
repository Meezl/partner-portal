<?php

use App\Enums\UserRole;
use App\Models\Conference;
use App\Models\Partner;
use App\Models\SponsorshipPackage;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('new partner users are redirected to the eoi page from dashboard', function () {
    $user = User::factory()->partner()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('partner.eoi.create'));
});

test('unverified new partner users can open the eoi page', function () {
    $user = User::factory()->partner()->unverified()->create();

    $response = $this->actingAs($user)->get(route('partner.eoi.create'));

    $response->assertOk();
});

test('partner users with an eoi are redirected to the partner dashboard', function () {
    $conference = Conference::factory()->active()->create();
    $package = SponsorshipPackage::factory()->create(['conference_id' => $conference->id]);
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()
        ->forUser($user)
        ->create(['conference_id' => $conference->id]);

    $partner->packages()->sync([$package->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('partner.dashboard'));
});

test('admin users are redirected to the admin dashboard', function () {
    $user = User::factory()->admin()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('admin.dashboard'));
});
