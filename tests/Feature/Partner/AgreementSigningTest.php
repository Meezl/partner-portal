<?php

use App\Enums\AgreementStatus;
use App\Enums\PartnerStatus;
use App\Models\Conference;
use App\Models\Partner;
use App\Models\SponsorshipPackage;
use App\Models\User;
use App\Notifications\AgreementSignedNotification;
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
        'tax_details' => 'PIN-1234567',
    ]);
    $partner->packages()->sync([$package->id]);

    test()->actingAs($user)->put(route('partner.commitment.update'), [
        'billing_address' => 'Billing Office, Nairobi',
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
        ->post(route('partner.agreement.sign'), ['signer_name' => 'Jane Partner', 'accept_terms' => true])
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
        ? $this->actingAs($user)->post(route('partner.agreement.sign'), ['signer_name' => 'Jane Partner', 'accept_terms' => true])
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
        ? $this->actingAs($user)->post(route('partner.agreement.sign'), ['signer_name' => 'Jane Partner', 'accept_terms' => true])
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
        ? $this->actingAs($user)->post(route('partner.agreement.sign'), ['signer_name' => 'Jane Partner', 'accept_terms' => true])
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
