<?php

use App\Enums\AgreementStatus;
use App\Enums\PartnerStatus;
use App\Models\Conference;
use App\Models\Partner;
use App\Models\SponsorshipPackage;
use App\Models\User;
use App\Notifications\AgreementSignedNotification;
use App\Notifications\NewPartnerNotification;
use App\Notifications\PartnerWelcomeNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** A partner who has confirmed their package and has an unsigned agreement waiting. */
function partnerAwaitingSignature(): array
{
    $conference = Conference::factory()->active()->create();
    $package = SponsorshipPackage::factory()->create(['conference_id' => $conference->id]);
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($user)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::PendingAgreement,
        'billing_address' => 'Billing Office, Nairobi',
        'billing_city' => 'Nairobi',
        'billing_country' => 'Kenya',
        'billing_postal_code' => '00100',
        'tax_details' => 'PIN-1234567',
    ]);
    $partner->packages()->sync([$package->id]);

    test()->actingAs($user)->put(route('partner.commitment.update'), [
        'billing_address' => 'Billing Office, Nairobi',
        'billing_city' => 'Nairobi',
        'billing_country' => 'Kenya',
        'billing_postal_code' => '00100',
        'tax_details' => 'PIN-1234567',
    ]);

    return [$user, $partner->fresh()];
}

function signedPdf(): UploadedFile
{
    return UploadedFile::fake()->create('signed-agreement.pdf', 64, 'application/pdf');
}

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

it('lets the partner sign digitally', function () {
    [$user, $partner] = partnerAwaitingSignature();

    $this->actingAs($user)
        ->post(route('partner.agreement.sign'), signingPayload())
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $agreement = $partner->agreements()->latest()->first();

    expect($agreement->status)->toBe(AgreementStatus::Signed)
        ->and($agreement->signed_method)->toBe('digital')
        ->and(Storage::disk('local')->get($agreement->signed_document_path))->toStartWith('%PDF');
});

it('lets the partner upload a wet-signed copy instead', function () {
    [$user, $partner] = partnerAwaitingSignature();

    $this->actingAs($user)
        ->post(route('partner.agreement.upload'), ['signed_document' => signedPdf()])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $agreement = $partner->agreements()->latest()->first();

    expect($agreement->status)->toBe(AgreementStatus::Signed)
        ->and($agreement->signed_method)->toBe('upload')
        ->and(Storage::disk('local')->exists($agreement->signed_document_path))->toBeTrue()
        ->and($partner->fresh()->status)->toBe(PartnerStatus::PendingPayment);
});

it('serves the partner their own signed copy once signed', function (string $method) {
    [$user, $partner] = partnerAwaitingSignature();

    $method === 'digital'
        ? $this->actingAs($user)->post(route('partner.agreement.sign'), signingPayload())
        : $this->actingAs($user)->post(route('partner.agreement.upload'), ['signed_document' => signedPdf()]);

    $agreement = $partner->agreements()->latest()->first();

    $this->actingAs($user)
        ->get(route('partner.agreement.download'))
        ->assertOk()
        ->assertDownload(Str::afterLast($agreement->signed_document_path, '/'));
})->with(['digital', 'upload']);

it('tells the team however the agreement was signed', function (string $method, string $line) {
    [$user] = partnerAwaitingSignature();

    $method === 'digital'
        ? $this->actingAs($user)->post(route('partner.agreement.sign'), signingPayload())
        : $this->actingAs($user)->post(route('partner.agreement.upload'), ['signed_document' => signedPdf()]);

    Notification::assertSentOnDemand(
        AgreementSignedNotification::class,
        fn (AgreementSignedNotification $notification, array $channels, object $notifiable) => in_array(
            $line,
            $notification->toMail($notifiable)->introLines,
            true,
        ),
    );
})->with([
    'digital' => ['digital', 'They signed digitally in the partner portal.'],
    'upload' => ['upload', 'They uploaded a signed copy — please check the signatures on the document.'],
]);

it('only accepts the signed copy as a PDF', function () {
    [$user, $partner] = partnerAwaitingSignature();

    $this->actingAs($user)
        ->post(route('partner.agreement.upload'), [
            'signed_document' => UploadedFile::fake()->create('signed.docx', 64, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->assertSessionHasErrors(['signed_document' => 'Upload the signed agreement as a PDF — scan or save all signed pages into one PDF file.']);

    expect($partner->agreements()->latest()->first()->status)->toBe(AgreementStatus::Pending);
});

it('does not let a second signature replace the first', function (string $first, string $second) {
    [$user, $partner] = partnerAwaitingSignature();
    $sign = fn (string $method) => $method === 'digital'
        ? $this->actingAs($user)->post(route('partner.agreement.sign'), signingPayload())
        : $this->actingAs($user)->post(route('partner.agreement.upload'), ['signed_document' => signedPdf()]);

    $sign($first);
    $signedCopy = $partner->agreements()->latest()->first()->signed_document_path;

    $sign($second)->assertSessionHas('error');

    $agreement = $partner->agreements()->latest()->first();

    expect($agreement->signed_method)->toBe($first)
        ->and($agreement->signed_document_path)->toBe($signedCopy);
})->with([
    'digital, then upload' => ['digital', 'upload'],
    'upload, then digital' => ['upload', 'digital'],
    'upload twice' => ['upload', 'upload'],
]);

it('announces a new partner to the partnerships mailbox and to partnerships users', function (string $method) {
    $teamMember = User::factory()->partnerships()->create(['email' => 'anne@amref.org']);
    $finance = User::factory()->finance()->create();
    [$user, $partner] = partnerAwaitingSignature();

    $method === 'digital'
        ? $this->actingAs($user)->post(route('partner.agreement.sign'), signingPayload())
        : $this->actingAs($user)->post(route('partner.agreement.upload'), ['signed_document' => signedPdf()]);

    // The partnerships team's own user accounts.
    Notification::assertSentTo($teamMember, NewPartnerNotification::class);

    // Finance is told about the invoice, not about a new relationship to own.
    Notification::assertNotSentTo($finance, NewPartnerNotification::class);

    // Plus the team mailbox, which is nobody's user account.
    Notification::assertSentOnDemand(
        NewPartnerNotification::class,
        fn (NewPartnerNotification $notification, array $channels, object $notifiable) => ($notifiable->routes['mail'] ?? null) === 'partnerships@amref.org'
            && str_contains($notification->toMail($notifiable)->subject, $partner->organization_name),
    );
})->with(['digital', 'upload']);

it('does not email the partnerships mailbox twice when it is also a user account', function () {
    config(['ahaic.partnerships_emails' => ['anne@amref.org']]);
    $teamMember = User::factory()->partnerships()->create(['email' => 'anne@amref.org']);
    [$user] = partnerAwaitingSignature();

    $this->actingAs($user)->post(route('partner.agreement.sign'), signingPayload());

    // Once in total: to the user account, and not again to the same address.
    Notification::assertSentTo($teamMember, NewPartnerNotification::class);
    Notification::assertSentOnDemandTimes(NewPartnerNotification::class, 0);
});

it('leaves out partnerships users who have been deactivated', function () {
    $former = User::factory()->partnerships()->create(['is_active' => false]);
    [$user] = partnerAwaitingSignature();

    $this->actingAs($user)->post(route('partner.agreement.sign'), signingPayload());

    Notification::assertNotSentTo($former, NewPartnerNotification::class);
});

it('welcomes the partner and points them at onboarding once they have signed', function (string $method) {
    [$user, $partner] = partnerAwaitingSignature();

    $method === 'digital'
        ? $this->actingAs($user)->post(route('partner.agreement.sign'), signingPayload())
        : $this->actingAs($user)->post(route('partner.agreement.upload'), ['signed_document' => signedPdf()]);

    Notification::assertSentTo(
        $user,
        PartnerWelcomeNotification::class,
        fn (PartnerWelcomeNotification $notification) => str_contains(
            $notification->toMail($user)->subject,
            'Partner Onboarding & Next Steps',
        ),
    );
})->with(['digital', 'upload']);

it('renders the welcome email with the wording the partnerships team supplied', function () {
    [$user, $partner] = partnerAwaitingSignature();

    $this->actingAs($user)->post(route('partner.agreement.sign'), signingPayload());

    // Fixed dates so the assertion below reads the conference record rather
    // than whatever the factory's "six months out" lands on today.
    $partner->conference->update(['start_date' => '2027-02-28', 'end_date' => '2027-03-03']);

    $notification = new PartnerWelcomeNotification(
        $partner->fresh()->agreements()->latest()->first(),
    );
    $mail = $notification->toMail($user);
    $body = (string) $mail->render();

    expect($mail->subject)->toContain('Partner Onboarding & Next Steps');

    // The headings, both checklists and the two links all have to survive the
    // markdown view, which is why the copy does not live in MailMessage lines.
    expect($body)
        ->toContain('Dear '.$partner->organization_name.' Team')
        ->toContain('Greetings from the AHAIC Team')
        ->toContain('From Dialogue to Delivery')
        ->toContain('Partner Portal Access')
        ->toContain('Access the AHAIC Partner Portal')
        ->toContain('What Happens Next?')
        ->toContain('Branding and logo requirements')
        ->toContain('Speaker profiles and biographies')
        ->toContain('https://ahaic.org')
        ->toContain('/partner/onboarding')
        ->toContain('Amref Health Africa');

    // The conference record drives the dates, not a second copy of them.
    expect($body)->toContain('28 February to 3 March 2027');
});
