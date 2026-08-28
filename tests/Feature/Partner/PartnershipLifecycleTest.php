<?php

use App\Enums\AgreementStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PartnerStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Conference;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\SponsorshipPackage;
use App\Models\User;
use App\Notifications\AgreementReadyNotification;
use App\Notifications\InterestApprovedNotification;
use App\Notifications\InterestRejectedNotification;
use App\Notifications\InvoiceSentNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function partnerFixture(array $partnerOverrides = []): array
{
    $conference = Conference::factory()->active()->create();
    $package = SponsorshipPackage::factory()->create(['conference_id' => $conference->id]);
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()
        ->forUser($user)
        ->create(array_merge([
            'conference_id' => $conference->id,
            'status' => PartnerStatus::InterestSubmitted,
        ], $partnerOverrides));

    $partner->packages()->sync([$package->id]);

    return compact('conference', 'package', 'user', 'partner');
}

it('approves an interested submission and emails the partner', function () {
    Notification::fake();

    ['partner' => $partner] = partnerFixture();
    $reviewer = User::factory()->partnerships()->create();

    $response = $this
        ->actingAs($reviewer)
        ->put(route('admin.partners.status', $partner), [
            'status' => PartnerStatus::PendingAgreement->value,
        ]);

    $response->assertRedirect();

    expect($partner->fresh()->status)->toBe(PartnerStatus::PendingAgreement);
    Notification::assertSentTo($partner->user, InterestApprovedNotification::class);
});

it('rejects an interested submission and emails the partner', function () {
    Notification::fake();

    ['partner' => $partner] = partnerFixture();
    $reviewer = User::factory()->partnerships()->create();

    $response = $this
        ->actingAs($reviewer)
        ->put(route('admin.partners.status', $partner), [
            'status' => PartnerStatus::Rejected->value,
        ]);

    $response->assertRedirect();

    expect($partner->fresh()->status)->toBe(PartnerStatus::Rejected);
    Notification::assertSentTo($partner->user, InterestRejectedNotification::class);
});

it('confirms commitment details and generates an agreement without invoicing yet', function () {
    Storage::fake('local');
    Notification::fake();

    ['user' => $user, 'partner' => $partner] = partnerFixture([
        'status' => PartnerStatus::PendingAgreement,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('partner.commitment.update'), [
            'billing_address' => 'Billing Office, Nairobi',
            'tax_details' => 'PIN-1234567',
        ]);

    $response->assertRedirect(route('partner.agreement.show'));

    $partner = $partner->fresh(['agreements', 'invoices']);
    $agreement = $partner->agreements->first();

    expect($partner->status)->toBe(PartnerStatus::PendingAgreement)
        ->and($partner->billing_address)->toBe('Billing Office, Nairobi')
        ->and($agreement)->not->toBeNull()
        ->and($agreement->status)->toBe(AgreementStatus::Pending)
        ->and($agreement->document_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($agreement->document_path))->toBeTrue()
        ->and($partner->invoices)->toHaveCount(0);

    Notification::assertSentTo($user, AgreementReadyNotification::class);
    Notification::assertNotSentTo($user, InvoiceSentNotification::class);
});

it('digitally signs the agreement, generates an invoice, and advances to pending payment', function () {
    Storage::fake('local');
    Notification::fake();

    ['user' => $user, 'partner' => $partner] = partnerFixture([
        'status' => PartnerStatus::PendingAgreement,
        'billing_address' => 'Billing Office, Nairobi',
        'tax_details' => 'PIN-1234567',
    ]);

    $this->actingAs($user)->put(route('partner.commitment.update'), [
        'billing_address' => 'Billing Office, Nairobi',
        'tax_details' => 'PIN-1234567',
    ]);

    Notification::fake();

    $response = $this
        ->actingAs($user)
        ->post(route('partner.agreement.sign'), [
            'signer_name' => 'Jane Partner',
            'accept_terms' => true,
        ]);

    $response->assertRedirect();

    $partner = $partner->fresh(['agreements', 'invoices']);
    $agreement = $partner->agreements->first();
    $invoice = $partner->invoices->first();

    expect($partner->status)->toBe(PartnerStatus::PendingPayment)
        ->and($agreement->status)->toBe(AgreementStatus::Signed)
        ->and($agreement->signed_method)->toBe('digital')
        ->and($agreement->signed_by_name)->toBe('Jane Partner')
        ->and($agreement->signed_document_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($agreement->signed_document_path))->toBeTrue()
        ->and($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->document_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($invoice->document_path))->toBeTrue();

    Notification::assertSentTo($user, InvoiceSentNotification::class);
});

it('accepts an uploaded signed agreement and exposes the invoice on the payment page', function () {
    Storage::fake('local');
    Notification::fake();

    ['user' => $user, 'partner' => $partner] = partnerFixture([
        'status' => PartnerStatus::PendingAgreement,
        'billing_address' => 'Billing Office, Nairobi',
        'tax_details' => 'PIN-1234567',
    ]);

    $this->actingAs($user)->put(route('partner.commitment.update'), [
        'billing_address' => 'Billing Office, Nairobi',
        'tax_details' => 'PIN-1234567',
    ]);

    Notification::fake();

    $response = $this
        ->actingAs($user)
        ->post(route('partner.agreement.upload'), [
            'signed_document' => UploadedFile::fake()->create('signed-agreement.pdf', 64, 'application/pdf'),
        ]);

    $response->assertRedirect();

    $partner = $partner->fresh(['agreements', 'invoices']);
    $agreement = $partner->agreements->first();
    $invoice = $partner->invoices->first();

    expect($agreement->signed_method)->toBe('upload')
        ->and($agreement->signed_document_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($agreement->signed_document_path))->toBeTrue()
        ->and($invoice)->not->toBeNull();

    $paymentPage = $this
        ->actingAs($user)
        ->get(route('partner.payment.create'));

    $paymentPage->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Partner/Payment')
        ->has('invoices', 1)
        ->where('invoices.0.id', $invoice->id));
});

it('records payment proof and lets finance confirm the payment', function () {
    Notification::fake();
    Storage::fake('local');

    ['partner' => $partner] = partnerFixture([
        'status' => PartnerStatus::PendingPayment,
    ]);

    $invoice = Invoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => InvoiceStatus::Sent,
    ]);

    $partnerUser = $partner->user;

    $submitResponse = $this
        ->actingAs($partnerUser)
        ->post(route('partner.payment.store'), [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'BANK-REF-001',
            'supporting_document' => UploadedFile::fake()->create('bank-transfer-slip.pdf', 256, 'application/pdf'),
        ]);

    $submitResponse->assertRedirect(route('partner.dashboard'));

    $payment = Payment::firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->currency)->toBe($invoice->currency)
        ->and($payment->payment_method)->toBe('bank_transfer')
        ->and($payment->supporting_document_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($payment->supporting_document_path))->toBeTrue();

    $financeUser = User::factory()->finance()->create([
        'role' => UserRole::Finance,
    ]);

    $proofDownloadResponse = $this
        ->actingAs($financeUser)
        ->get(route('admin.finance.payments.proof', $payment));

    $proofDownloadResponse->assertOk();

    $confirmResponse = $this
        ->actingAs($financeUser)
        ->put(route('admin.finance.payments.confirm', $payment));

    $confirmResponse->assertRedirect();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($partner->fresh()->status)->toBe(PartnerStatus::Confirmed)
        ->and($partner->fresh()->confirmed_at)->not->toBeNull();

    Notification::assertSentTo($partnerUser, PaymentConfirmedNotification::class);
});

it('requires a bank transfer proof document for payment submission', function () {
    Storage::fake('local');

    ['partner' => $partner] = partnerFixture([
        'status' => PartnerStatus::PendingPayment,
    ]);

    $invoice = Invoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => InvoiceStatus::Sent,
    ]);

    $response = $this
        ->actingAs($partner->user)
        ->post(route('partner.payment.store'), [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'payment_method' => 'card',
            'transaction_reference' => 'INVALID-REF-001',
        ]);

    $response->assertSessionHasErrors([
        'payment_method',
        'supporting_document',
    ]);

    expect(Payment::count())->toBe(0);
});

it('stores a database notification for finance when payment proof is submitted', function () {
    Storage::fake('local');

    ['partner' => $partner] = partnerFixture([
        'status' => PartnerStatus::PendingPayment,
    ]);

    $invoice = Invoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => InvoiceStatus::Sent,
    ]);

    $financeUser = User::factory()->finance()->create(['role' => UserRole::Finance]);

    $this->actingAs($partner->user)
        ->post(route('partner.payment.store'), [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'BANK-REF-DB-001',
            'supporting_document' => UploadedFile::fake()->create('slip.pdf', 128, 'application/pdf'),
        ])
        ->assertRedirect(route('partner.dashboard'));

    // The notification declares the 'database' channel, so the notifications
    // table must exist and the row must name the partner correctly.
    $stored = DB::table('notifications')
        ->where('notifiable_id', $financeUser->id)
        ->where('type', PaymentSubmittedNotification::class)
        ->first();

    expect($stored)->not->toBeNull();

    $data = json_decode($stored->data, true);

    expect($data['payment_id'])->toBe(Payment::firstOrFail()->id)
        ->and($data['message'])->toContain($partner->organization_name)
        ->and($data['message'])->not->toContain('A Partner');
});
