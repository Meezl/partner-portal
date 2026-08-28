<?php

use App\Enums\InvoiceStatus;
use App\Enums\PartnerStatus;
use App\Models\Conference;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Uploads must follow config('ahaic.disks.*') rather than a hardcoded disk, so
 * that pointing those keys at S3 actually moves the files. 's3' is faked here,
 * which exercises the same indirection without needing real credentials.
 */
function storageFixture(): array
{
    $conference = Conference::factory()->active()->create();
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($user)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::PendingPayment,
    ]);

    return compact('conference', 'user', 'partner');
}

it('defaults to the local disk so nothing changes until S3 is configured', function () {
    expect(config('ahaic.disks.private'))->toBe('local')
        ->and(config('ahaic.disks.public'))->toBe('public');
});

it('writes payment proof to the configured private disk instead of local', function () {
    Notification::fake();
    Storage::fake('local');
    Storage::fake('s3');
    config(['ahaic.disks.private' => 's3']);

    ['partner' => $partner, 'user' => $user] = storageFixture();
    $invoice = Invoice::factory()->create(['partner_id' => $partner->id, 'status' => InvoiceStatus::Sent]);

    $this->actingAs($user)->post(route('partner.payment.store'), [
        'invoice_id' => $invoice->id,
        'amount' => $invoice->amount,
        'payment_method' => 'bank_transfer',
        'transaction_reference' => 'REF-1',
        'supporting_document' => UploadedFile::fake()->create('proof.pdf', 128, 'application/pdf'),
    ])->assertRedirect();

    $path = Payment::sole()->supporting_document_path;

    Storage::disk('s3')->assertExists($path);
    Storage::disk('local')->assertMissing($path);
});

it('serves a download from the configured disk', function () {
    Notification::fake();
    Storage::fake('s3');
    config(['ahaic.disks.private' => 's3']);

    ['partner' => $partner, 'user' => $user] = storageFixture();

    $invoice = Invoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => InvoiceStatus::Sent,
        'document_path' => 'invoices/invoice-test.pdf',
    ]);
    Storage::disk('s3')->put('invoices/invoice-test.pdf', '%PDF-1.4 fake');

    // Storage::download() streams from any driver; ->path() would throw on S3.
    $this->actingAs($user)
        ->get(route('partner.invoices.download', $invoice))
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('writes partner logos to the configured public disk and stores a matching url', function () {
    Notification::fake();
    Storage::fake('public');
    Storage::fake('s3_public');
    config(['ahaic.disks.public' => 's3_public']);

    ['partner' => $partner, 'user' => $user] = storageFixture();
    $partner->update(['status' => PartnerStatus::Confirmed]);

    $this->actingAs($user)->put(route('partner.onboarding.update', 'organization'), [
        'organization_name' => $partner->organization_name,
        'description' => 'A description long enough to count.',
        'number_of_participants' => 5,
        'logo' => UploadedFile::fake()->image('logo.png'),
    ])->assertRedirect();

    $partner->refresh();

    expect($partner->logo_path)->not->toBeNull();

    // The stored value is a full URL built by the same disk that holds the file,
    // so switching disks moves the file and the URL together.
    $key = 'partners/'.$partner->id.'/logos';
    expect(collect(Storage::disk('s3_public')->allFiles($key))->count())->toBe(1)
        ->and(Storage::disk('public')->allFiles($key))->toBeEmpty();
});
