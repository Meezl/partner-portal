<?php

use App\Enums\PackageTier;
use App\Models\Agreement;
use App\Models\Conference;
use App\Models\Partner;
use App\Models\SponsorshipPackage;
use App\Services\AgreementGeneratorService;
use App\Services\PartnershipAgreementTerms;
use Illuminate\Support\Facades\Storage;

/** The agreement a partner on this package would be issued, as plain text. */
function agreementTextFor(SponsorshipPackage $package, ?Agreement $agreement = null): string
{
    $partner = Partner::factory()->create([
        'conference_id' => $package->conference_id,
        'organization_name' => 'Kijiji Health Trust',
        'physical_address' => 'P.O. Box 123 - 00100 Nairobi',
    ]);
    $partner->packages()->sync([$package->id]);

    $agreement ??= Agreement::factory()->create(['partner_id' => $partner->id, 'generated_at' => '2026-08-27 10:00:00']);

    $html = view('pdf.agreement', [
        'partner' => $partner,
        'package' => $package,
        'agreement' => $agreement,
        'terms' => PartnershipAgreementTerms::for($agreement, $partner, $package),
    ])->render();

    // Tags become spaces so a hanging clause number stays apart from its text.
    $html = preg_replace(['#<style>.*?</style>#s', '/<[^>]+>/'], ' ', $html);

    return preg_replace('/\s+/u', ' ', html_entity_decode($html));
}

function packageOnTier(PackageTier $tier, float $price): SponsorshipPackage
{
    $conference = Conference::factory()->active()->create([
        'year' => 2027,
        'start_date' => '2027-02-28',
        'end_date' => '2027-03-03',
    ]);

    return SponsorshipPackage::factory()->create([
        'conference_id' => $conference->id,
        'tier' => $tier,
        'price' => $price,
    ]);
}

it('issues each tier its own partnership terms', function (PackageTier $tier, float $price, string $amount, string $amrefRole, string $partnerRole, bool $indemnity) {
    $text = agreementTextFor(packageOnTier($tier, $price));

    expect($text)
        ->toContain($tier->label().' Partnership Package;')
        ->toContain("a total amount of {$amount} (the “Partner Contribution”)")
        ->toContain($amrefRole)
        ->toContain($partnerRole);

    $indemnity
        ? expect($text)->toContain('12. INDEMNITY')
        : expect($text)->not->toContain('INDEMNITY');
})->with([
    'diamond' => [PackageTier::Diamond, 200000, '$200,000', 'Issue 12 complimentary conference tickets', 'Pay for the Diamond Package ($200,000);', true],
    'platinum' => [PackageTier::Platinum, 150000, '$150,000', 'Avail a standard 5m by 3m exhibition booth', 'Pay for the Platinum Package ($150,000);', true],
    'gold' => [PackageTier::Gold, 100000, '$100,000', 'Issue 8 Complimentary conference tickets (3 VIP, 5 Standard)', 'Pay for the Gold Package ($100,000);', true],
    'silver' => [PackageTier::Silver, 50000, '$50,000', 'Allow Partner to display a digital banner at the conference center.', 'Pay for the Silver Package ($50,000);', true],
    'cso' => [PackageTier::Cso, 25000, '$25,000', 'Issue 3 regular complimentary conference tickets', 'Identify the Global South CSO or delegates', false],
    'exhibitor' => [PackageTier::Exhibitor, 7500, '$7,500', 'Issue 2 regular complimentary conference tickets', 'Pay for the Exhibitor Partnership Package ($7,500);', false],
]);

it('uses "an" before a package name that starts with a vowel', function () {
    expect(agreementTextFor(packageOnTier(PackageTier::Exhibitor, 7500)))
        ->toContain('in the form of an Exhibitor Partnership Package');
});

it('only gives the collaboration clauses to the tiers whose agreements carry them', function () {
    expect(agreementTextFor(packageOnTier(PackageTier::Gold, 100000)))
        ->toContain('1. PARTNER CONTRIBUTION AND COLLABORATION')
        ->toContain('1.2 Amref shall use the Partner Contribution exclusively for the Conference.');

    expect(agreementTextFor(packageOnTier(PackageTier::Silver, 50000)))
        ->toContain('1. PARTNER CONTRIBUTION 1.1 The Partner agrees')
        ->not->toContain('AND COLLABORATION')
        ->not->toContain('exclusively for the Conference');
});

it('takes the amount from the package, so a price change reaches the agreement', function () {
    expect(agreementTextFor(packageOnTier(PackageTier::Gold, 120000)))
        ->toContain('a total amount of $120,000')
        ->toContain('Pay for the Gold Package ($120,000);');
});

it('fills in the blanks the templates left for the partner, date and duration', function () {
    // Made 27 Aug 2026; the report is due a month after the 3 Mar 2027 close,
    // so the agreement runs a whole eight months, to 27 Apr 2027.
    expect(agreementTextFor(packageOnTier(PackageTier::Gold, 100000)))
        ->toContain('is made this 27th day of August 2026')
        ->toContain('Kijiji Health Trust whose address is P.O. Box 123 - 00100 Nairobi')
        ->toContain('shall hold the 7th edition in 2027')
        ->toContain('come into force on August 27, 2026 and shall continue for eight (8) months ending on April 27, 2027');
});

it('shows a digital signature in the partner\'s signature block', function () {
    $package = packageOnTier(PackageTier::Silver, 50000);
    $agreement = Agreement::factory()->create([
        'signed_by_name' => 'Wanjiru Kamau',
        'signed_method' => 'digital',
        'signed_at' => '2026-09-01 09:30:00',
    ]);

    expect(agreementTextFor($package, $agreement))
        ->toContain('Name: Wanjiru Kamau')
        ->toContain('Signature: Signed digitally')
        ->toContain('Date: September 1, 2026');
});

it('falls back to the package benefits for a tier with no transcribed terms', function () {
    $package = packageOnTier(PackageTier::Gold, 5000);
    config()->set('agreements.tiers.gold', null);
    $package->update(['name' => 'Media', 'benefits' => ['Press accreditation for two']]);

    expect(agreementTextFor($package))
        ->toContain('3.1.1 Press accreditation for two')
        ->toContain('Pay for the Media Package ($5,000);');
});

it('stores the tier agreement when a partner confirms their package', function () {
    Storage::fake('local');
    $package = packageOnTier(PackageTier::Diamond, 200000);
    $partner = Partner::factory()->create(['conference_id' => $package->conference_id]);
    $partner->packages()->sync([$package->id]);

    $agreement = app(AgreementGeneratorService::class)->generate($partner);

    expect(Storage::disk('local')->exists($agreement->document_path))->toBeTrue()
        ->and(Storage::disk('local')->get($agreement->document_path))->toStartWith('%PDF');
});
