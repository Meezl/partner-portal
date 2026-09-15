<?php

use App\Enums\AgreementStatus;
use App\Enums\PartnerStatus;
use App\Enums\UserRole;
use App\Models\Agreement;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\SponsorshipPackage;
use App\Models\User;
use App\Notifications\AgreementRejectedNotification;
use App\Notifications\AgreementVerifiedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/** An agreement the partner has uploaded a signed copy of, awaiting review. */
function signedAgreement(array $partner = []): Agreement
{
    Storage::disk('local')->put('agreements/signed.pdf', '%PDF-1.4 signed');

    return Agreement::factory()->create([
        'partner_id' => Partner::factory()->create(['status' => PartnerStatus::PendingPayment, ...$partner])->id,
        'signed_document_path' => 'agreements/signed.pdf',
        'signed_by_name' => 'Jane Partner',
        'signed_method' => 'upload',
        'signed_at' => now(),
        'status' => AgreementStatus::Signed,
    ]);
}

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

it('is open to the partnerships team and admins', function (string $role) {
    $agreement = signedAgreement();

    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get(route('admin.agreements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Agreements/Index')
            ->where('status', 'signed')
            ->where('counts.signed', 1)
            ->where('agreements.0.id', $agreement->id));
})->with(['partnerships', 'admin', 'super_admin']);

it('is closed to other staff and to partners', function (string $role) {
    $agreement = signedAgreement();
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('admin.agreements.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.agreements.verify', $agreement))->assertForbidden();

    expect($agreement->fresh()->status)->toBe(AgreementStatus::Signed);
})->with(['finance', 'programme', 'pco', 'communications', 'partner']);

it('shows the reviewer the signed document', function () {
    $agreement = signedAgreement();

    $response = $this->actingAs(User::factory()->partnerships()->create())
        ->get(route('admin.agreements.document', $agreement))
        ->assertOk();

    expect($response->streamedContent())->toBe('%PDF-1.4 signed');
});

it('verifies a signed agreement and tells the partner', function () {
    $agreement = signedAgreement();
    $reviewer = User::factory()->partnerships()->create();

    $this->actingAs($reviewer)
        ->post(route('admin.agreements.verify', $agreement))
        ->assertSessionHas('success');

    $agreement->refresh();

    expect($agreement->status)->toBe(AgreementStatus::Verified)
        ->and($agreement->reviewed_by)->toBe($reviewer->id)
        ->and($agreement->reviewed_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'agreement_verified')->where('auditable_id', $agreement->id)->exists())->toBeTrue();

    Notification::assertSentTo($agreement->partner->user, AgreementVerifiedNotification::class);
});

it('sends an agreement back with a reason and reopens it for signing', function () {
    $agreement = signedAgreement();

    $this->actingAs(User::factory()->partnerships()->create())
        ->post(route('admin.agreements.reject', $agreement), ['reason' => 'Page 3 is not signed.'])
        ->assertSessionHas('success');

    $agreement->refresh();

    expect($agreement->status)->toBe(AgreementStatus::Rejected)
        ->and($agreement->review_notes)->toBe('Page 3 is not signed.')
        ->and($agreement->signed_document_path)->toBeNull()
        ->and($agreement->signed_method)->toBeNull()
        ->and($agreement->partner->status)->toBe(PartnerStatus::PendingAgreement)
        // The rejected copy is kept, and the audit log says where.
        ->and(Storage::disk('local')->exists('agreements/signed.pdf'))->toBeTrue()
        ->and(AuditLog::where('action', 'agreement_rejected')->first()->old_values)
        ->toMatchArray(['signed_document_path' => 'agreements/signed.pdf', 'reason' => 'Page 3 is not signed.']);

    Notification::assertSentTo(
        $agreement->partner->user,
        AgreementRejectedNotification::class,
        fn (AgreementRejectedNotification $notification, array $channels, object $notifiable) => in_array(
            'Reason: Page 3 is not signed.',
            $notification->toMail($notifiable)->introLines,
            true,
        ),
    );
});

it('will not send an agreement back without saying why', function () {
    $agreement = signedAgreement();

    $this->actingAs(User::factory()->partnerships()->create())
        ->post(route('admin.agreements.reject', $agreement), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($agreement->fresh()->status)->toBe(AgreementStatus::Signed);
});

it('does not move a partner who has already paid back to the agreement step', function () {
    $agreement = signedAgreement(['status' => PartnerStatus::Confirmed]);

    $this->actingAs(User::factory()->partnerships()->create())
        ->post(route('admin.agreements.reject', $agreement), ['reason' => 'Wrong signatory.']);

    expect($agreement->partner->fresh()->status)->toBe(PartnerStatus::Confirmed);
});

it('only reviews agreements that are signed', function (AgreementStatus $status) {
    $agreement = Agreement::factory()->create(['status' => $status]);
    $reviewer = User::factory()->partnerships()->create();

    $this->actingAs($reviewer)->post(route('admin.agreements.verify', $agreement))->assertSessionHas('error');
    $this->actingAs($reviewer)->post(route('admin.agreements.reject', $agreement), ['reason' => 'x'])->assertSessionHas('error');

    expect($agreement->fresh()->status)->toBe($status);
})->with([AgreementStatus::Pending, AgreementStatus::Verified, AgreementStatus::Rejected]);

it('lets the partner sign again after the agreement is sent back, without a second invoice', function () {
    // Sign → rejected → sign again, through the real partner routes.
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($user)->create([
        'status' => PartnerStatus::PendingAgreement,
        'billing_address' => 'Billing Office, Nairobi',
        'tax_details' => 'PIN-1234567',
    ]);
    $partner->packages()->sync([SponsorshipPackage::factory()->create(['conference_id' => $partner->conference_id])->id]);
    $this->actingAs($user)->put(route('partner.commitment.update'), [
        'billing_address' => 'Billing Office, Nairobi',
        'tax_details' => 'PIN-1234567',
    ]);
    $upload = fn () => $this->actingAs($user)->post(route('partner.agreement.upload'), [
        'signed_document' => UploadedFile::fake()->create('signed.pdf', 64, 'application/pdf'),
    ]);

    $upload()->assertSessionHas('success');
    $agreement = $partner->agreements()->latest()->first();

    $this->actingAs(User::factory()->partnerships()->create())
        ->post(route('admin.agreements.reject', $agreement), ['reason' => 'Unsigned.']);

    $this->actingAs($user)->get(route('partner.agreement.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('agreement.status', 'rejected')
            ->where('agreement.review_notes', 'Unsigned.'));

    $upload()->assertSessionHas('success');

    expect($agreement->fresh()->status)->toBe(AgreementStatus::Signed)
        ->and($partner->fresh()->status)->toBe(PartnerStatus::PendingPayment)
        ->and($partner->invoices()->count())->toBe(1);
});

it('lists agreements by status', function () {
    signedAgreement();
    $verified = Agreement::factory()->create(['status' => AgreementStatus::Verified, 'reviewed_at' => now()]);

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get(route('admin.agreements.index', ['status' => 'verified']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'verified')
            ->has('agreements', 1)
            ->where('agreements.0.id', $verified->id)
            ->where('counts.signed', 1)
            ->where('counts.verified', 1));
});
