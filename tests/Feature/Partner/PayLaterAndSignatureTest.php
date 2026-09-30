<?php

use App\Enums\AgreementStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PartnerStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Conference;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\SponsorshipPackage;
use App\Models\User;
use App\Services\AgreementGeneratorService;
use App\Services\PartnershipAgreementTerms;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function partnerWithInvoice(): array
{
    $conference = Conference::factory()->active()->create();
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($user)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::PendingPayment,
    ]);
    $invoice = Invoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => InvoiceStatus::Sent,
        'amount' => 25000,
        'currency' => 'USD',
    ]);

    return [$user, $partner, $invoice];
}

/* ------------------------------ Pay now / pay later ----------------------- */

it('offers both ways of settling an invoice', function () {
    [$user] = partnerWithInvoice();

    $this->actingAs($user)
        ->get(route('partner.payment.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Partner/Payment')
            ->where('paymentTypes.0.value', 'proof_of_payment')
            ->where('paymentTypes.0.label', 'Paid now')
            ->where('paymentTypes.1.value', 'purchase_order')
            ->where('paymentTypes.1.label', 'Pay later (LPO/PO)')
            ->where('paymentTypes.1.document', 'LPO / purchase order'),
        );
});

it('accepts a purchase order for a partner paying later', function () {
    Storage::fake('local');

    [$user, $partner, $invoice] = partnerWithInvoice();

    $this->actingAs($user)
        ->post(route('partner.payment.store'), [
            'invoice_id' => $invoice->id,
            'payment_type' => 'purchase_order',
            'amount' => 25000,
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'LPO-2027-0044',
            'supporting_document' => UploadedFile::fake()->create('lpo.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('partner.dashboard'))
        ->assertSessionHas('success');

    $payment = Payment::firstOrFail();

    expect($payment->payment_type)->toBe(PaymentType::PurchaseOrder)
        ->and($payment->transaction_reference)->toBe('LPO-2027-0044')
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->supporting_document_path)->not->toBeNull();
});

it('settles the invoice when finance approves a payment, but not a purchase order', function () {
    [, $partner, $invoice] = partnerWithInvoice();
    $finance = User::factory()->admin()->create();

    $purchaseOrder = Payment::factory()->create([
        'partner_id' => $partner->id,
        'invoice_id' => $invoice->id,
        'payment_type' => PaymentType::PurchaseOrder,
        'status' => PaymentStatus::Pending,
    ]);

    $this->actingAs($finance)
        ->put(route('admin.finance.payments.confirm', $purchaseOrder))
        ->assertSessionHas('success');

    // Approved, and the partner moves on — but the money has not arrived, so
    // the invoice stays open for finance to chase.
    expect($purchaseOrder->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($partner->fresh()->status)->toBe(PartnerStatus::Confirmed)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->fresh()->paid_at)->toBeNull();

    $proof = Payment::factory()->create([
        'partner_id' => $partner->id,
        'invoice_id' => $invoice->id,
        'payment_type' => PaymentType::ProofOfPayment,
        'status' => PaymentStatus::Pending,
    ]);

    $this->actingAs($finance)
        ->put(route('admin.finance.payments.confirm', $proof))
        ->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->paid_at)->not->toBeNull();
});

it('treats a submission with no chosen type as money already sent', function () {
    Storage::fake('local');

    [$user, , $invoice] = partnerWithInvoice();

    $this->actingAs($user)
        ->post(route('partner.payment.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 25000,
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'TRX-1',
            'supporting_document' => UploadedFile::fake()->create('slip.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('partner.dashboard'));

    expect(Payment::firstOrFail()->payment_type)->toBe(PaymentType::ProofOfPayment);
});

/* -------------------------------- Signing -------------------------------- */

function partnerReadyToSign(): array
{
    $conference = Conference::factory()->active()->create();
    $package = SponsorshipPackage::factory()->create(['conference_id' => $conference->id]);
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($user)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::PendingAgreement,
        'contact_title' => 'Executive Director',
    ]);
    $partner->packages()->sync([$package->id]);

    app(AgreementGeneratorService::class)->generate($partner->fresh('packages'));

    return [$user, $partner];
}

it('records the signatory title, signature and witness when signing', function () {
    Storage::fake('local');

    [$user, $partner] = partnerReadyToSign();

    $this->actingAs($user)
        ->post(route('partner.agreement.sign'), signingPayload())
        ->assertSessionHas('success');

    $agreement = $partner->agreements()->latest()->firstOrFail();

    expect($agreement->status)->toBe(AgreementStatus::Signed)
        ->and($agreement->signed_by_name)->toBe('Jane Partner')
        ->and($agreement->signed_by_title)->toBe('Executive Director')
        ->and($agreement->signature_image)->toStartWith('data:image/png;base64,')
        ->and($agreement->witness_name)->toBe('Ken Witness')
        ->and($agreement->witness_title)->toBe('Finance Manager')
        ->and($agreement->witness_signature_image)->toStartWith('data:image/png;base64,');
});

it('refuses to sign without a drawn signature or a witness', function () {
    [$user] = partnerReadyToSign();

    $this->actingAs($user)
        ->post(route('partner.agreement.sign'), signingPayload([
            'signature_image' => null,
            'witness_name' => '',
            'witness_signature_image' => null,
        ]))
        ->assertSessionHasErrors(['signature_image', 'witness_name', 'witness_signature_image']);
});

it('rejects a signature that is not a drawn PNG', function () {
    [$user] = partnerReadyToSign();

    // A base64 payload that decodes to something other than a PNG.
    $this->actingAs($user)
        ->post(route('partner.agreement.sign'), signingPayload([
            'signature_image' => 'data:image/png;base64,'.base64_encode('<svg onload="alert(1)">'),
        ]))
        ->assertSessionHasErrors('signature_image');

    // And a plain string that is not a data URL at all.
    $this->actingAs($user)
        ->post(route('partner.agreement.sign'), signingPayload([
            'signature_image' => 'https://example.org/signature.png',
        ]))
        ->assertSessionHasErrors('signature_image');
});

it('prints the signature, title and witness on the signed agreement', function () {
    Storage::fake('local');

    [$user, $partner] = partnerReadyToSign();

    $this->actingAs($user)
        ->post(route('partner.agreement.sign'), signingPayload())
        ->assertSessionHas('success');

    $agreement = $partner->agreements()->latest()->firstOrFail();

    expect($agreement->signed_document_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($agreement->signed_document_path))->toBeTrue();

    // Rendering the view is what proves the signature block reads the new
    // fields; the PDF itself is a binary blob.
    $html = view('pdf.agreement', [
        'agreement' => $agreement,
        'partner' => $partner->fresh('packages'),
        'package' => $partner->packages->first(),
        'terms' => PartnershipAgreementTerms::for(
            $agreement,
            $partner,
            $partner->packages->first(),
        ),
    ])->render();

    expect($html)->toContain('Executive Director')
        ->and($html)->toContain('Ken Witness')
        ->and($html)->toContain('Finance Manager')
        ->and(substr_count($html, 'data:image/png;base64,'))->toBeGreaterThanOrEqual(2);
});

it('captures the signatory title at registration', function () {
    $conference = Conference::factory()->active()->create();
    $package = SponsorshipPackage::factory()->create(['conference_id' => $conference->id]);
    $user = User::factory()->partner()->create();

    $this->actingAs($user)
        ->post(route('partner.eoi.store'), [
            'organization_name' => 'Grit NGO',
            'contact_person' => 'Amina Odhiambo',
            'contact_title' => 'Chief Executive',
            'email' => 'amina@example.org',
            'phone' => '+254700000000',
            'physical_address' => 'Nairobi',
            'package_id' => $package->id,
        ]);

    expect(Partner::firstOrFail()->contact_title)->toBe('Chief Executive');
});
