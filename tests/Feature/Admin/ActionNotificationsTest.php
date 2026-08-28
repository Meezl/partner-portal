<?php

use App\Enums\AgreementStatus;
use App\Enums\PartnerStatus;
use App\Enums\PaymentStatus;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\Agreement;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Room;
use App\Models\SessionSchedule;
use App\Models\TimeSlot;
use App\Models\User;
use App\Notifications\AgendaPublishedNotification;
use App\Notifications\AgreementSignedNotification;
use App\Notifications\ChangeRequestSubmittedNotification;
use App\Notifications\EmailChangedNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\SessionUnscheduledNotification;
use App\Notifications\StaffAccountCreatedNotification;
use App\Notifications\SubmissionLockedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

function notifyFixture(): array
{
    $conference = Conference::factory()->active()->create();
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($partnerUser)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Confirmed,
    ]);
    $admin = User::factory()->admin()->create();

    return compact('conference', 'partner', 'partnerUser', 'admin');
}

it('tells the partner when finance rejects their payment', function () {
    Notification::fake();

    ['partner' => $partner, 'partnerUser' => $partnerUser, 'admin' => $admin] = notifyFixture();

    $invoice = Invoice::factory()->create(['partner_id' => $partner->id]);
    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => PaymentStatus::Pending,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.finance.payments.reject', $payment), ['reason' => 'Reference number did not match.'])
        ->assertSessionHas('success');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);

    Notification::assertSentTo($partnerUser, PaymentRejectedNotification::class);
});

it('tells every partner when the agenda is published', function () {
    Notification::fake();

    ['conference' => $conference, 'partnerUser' => $partnerUser, 'admin' => $admin] = notifyFixture();

    $otherUser = User::factory()->partner()->create();
    Partner::factory()->forUser($otherUser)->create(['conference_id' => $conference->id]);

    $this->actingAs($admin)
        ->post(route('admin.agenda.publish'))
        ->assertSessionHas('success');

    Notification::assertSentTo($partnerUser, AgendaPublishedNotification::class);
    Notification::assertSentTo($otherUser, AgendaPublishedNotification::class);
});

it('tells the partner when their session is unscheduled from the board', function () {
    Notification::fake();

    ['conference' => $conference, 'partner' => $partner, 'partnerUser' => $partnerUser, 'admin' => $admin] = notifyFixture();

    $session = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);
    $room = Room::factory()->create(['conference_id' => $conference->id, 'name' => 'MH1']);
    $timeSlot = TimeSlot::factory()->create([
        'conference_id' => $conference->id,
        'date' => '2027-03-02',
        'start_time' => '14:00:00',
        'end_time' => '15:30:00',
    ]);
    SessionSchedule::factory()->create([
        'conference_session_id' => $session->id,
        'room_id' => $room->id,
        'time_slot_id' => $timeSlot->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.scheduling.destroy', $session))
        ->assertSessionHas('success');

    Notification::assertSentTo($partnerUser, SessionUnscheduledNotification::class);
});

it('tells the partnerships team when a partner raises a change request', function () {
    Notification::fake();

    ['conference' => $conference, 'partner' => $partner, 'partnerUser' => $partnerUser] = notifyFixture();

    $session = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    $this->actingAs($partnerUser)
        ->post(route('partner.change-requests.store'), [
            'conference_session_id' => $session->id,
            'type' => 'other',
            'requested_value' => 'Please add a second microphone.',
            'reason' => 'Two presenters.',
        ])
        ->assertRedirect();

    Notification::assertSentOnDemand(ChangeRequestSubmittedNotification::class);
});

it('emails a new staff member instead of echoing their password back', function () {
    Notification::fake();

    ['admin' => $admin] = notifyFixture();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New Programme Officer',
            'email' => 'new.officer@example.com',
            'role' => UserRole::Programme->value,
            'password' => '',
            'is_active' => true,
        ])
        ->assertSessionHas('success');

    $created = User::where('email', 'new.officer@example.com')->sole();

    Notification::assertSentTo($created, StaffAccountCreatedNotification::class);

    // The generated credential must not travel back in the flash message.
    expect(session('success'))->not->toContain('Temporary password');
});

it('confirms a password change to the account owner', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(Hash::check('a-brand-new-password', $user->refresh()->password))->toBeTrue();

    Notification::assertSentTo($user, PasswordChangedNotification::class);
});

it('finalizes partners and sessions without fatalling, and notifies them', function () {
    Notification::fake();

    ['conference' => $conference, 'partner' => $partner, 'partnerUser' => $partnerUser, 'admin' => $admin] = notifyFixture();

    $partner->update(['status' => PartnerStatus::Submitted]);

    $session = ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
        'status' => SessionStatus::Scheduled,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.finalization.lock'))
        ->assertSessionHas('success');

    expect($partner->fresh()->status)->toBe(PartnerStatus::Finalized)
        ->and($partner->fresh()->locked_at)->not->toBeNull()
        ->and($session->fresh()->status)->toBe(SessionStatus::Confirmed);

    Notification::assertSentTo($partnerUser, SubmissionLockedNotification::class);
});

it('warns the previous address when an account email is changed', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'original@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'attacker@example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->email)->toBe('attacker@example.com');

    // The warning goes to the address being replaced, not the new one.
    Notification::assertSentOnDemand(
        EmailChangedNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'original@example.com',
    );
});

it('does not warn when the profile changes but the email does not', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'stable@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'A New Display Name',
            'email' => 'stable@example.com',
        ])
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

it('tells the team when a partner signs their agreement', function () {
    Notification::fake();

    ['partner' => $partner, 'partnerUser' => $partnerUser] = notifyFixture();

    Agreement::factory()->create([
        'partner_id' => $partner->id,
        'status' => AgreementStatus::Pending,
    ]);

    $this->actingAs($partnerUser)
        ->post(route('partner.agreement.sign'), [
            'signer_name' => 'A Signatory',
            'accept_terms' => true,
        ]);

    Notification::assertSentOnDemand(AgreementSignedNotification::class);
});
