<?php

use App\Models\Conference;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Finance and the admins reconcile payments against the invoice itself, so the
 * partner's profile has to hand them the same PDF the partner was sent.
 */
function invoiceFixture(): array
{
    Storage::fake('local');

    $conference = Conference::factory()->active()->create();
    $partner = Partner::factory()->forUser(User::factory()->partner()->create())->create([
        'conference_id' => $conference->id,
    ]);

    $invoice = Invoice::factory()->create([
        'partner_id' => $partner->id,
        'invoice_number' => 'AHAIC2027INV01',
        'document_path' => 'invoices/invoice_1.pdf',
    ]);

    Storage::disk('local')->put($invoice->document_path, '%PDF-1.4 invoice');

    return [$partner, $invoice];
}

it('lets finance download a partner invoice', function () {
    [, $invoice] = invoiceFixture();

    $this->actingAs(User::factory()->finance()->create())
        ->get(route('admin.finance.invoices.document', $invoice))
        ->assertOk()
        ->assertDownload('AHAIC2027INV01.pdf');
});

it('lets an admin download a partner invoice', function () {
    [, $invoice] = invoiceFixture();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.finance.invoices.document', $invoice))
        ->assertOk()
        ->assertDownload('AHAIC2027INV01.pdf');
});

it('keeps the invoice document away from the other staff roles', function () {
    [, $invoice] = invoiceFixture();

    $this->actingAs(User::factory()->partnerships()->create())
        ->get(route('admin.finance.invoices.document', $invoice))
        ->assertForbidden();
});

it('404s when the invoice has no document yet', function () {
    [$partner] = invoiceFixture();

    $pending = Invoice::factory()->create([
        'partner_id' => $partner->id,
        'document_path' => null,
    ]);

    $this->actingAs(User::factory()->finance()->create())
        ->get(route('admin.finance.invoices.document', $pending))
        ->assertNotFound();
});

it('offers the invoice column to finance but not to the other staff roles', function () {
    [$partner] = invoiceFixture();

    $this->actingAs(User::factory()->finance()->create())
        ->get(route('admin.partners.show', $partner))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Partners/Show')
            ->where('userRole', 'finance')
            ->has('partner.invoices', 1),
        );

    $this->actingAs(User::factory()->partnerships()->create())
        ->get(route('admin.partners.show', $partner))
        ->assertInertia(fn (Assert $page) => $page->where('userRole', 'partnerships'));
});
