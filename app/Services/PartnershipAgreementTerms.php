<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\Partner;
use App\Models\SponsorshipPackage;
use Carbon\CarbonInterface;
use Illuminate\Support\Number;

/**
 * Everything the partnership agreement template needs that is not fixed text:
 * the clauses for the partner's package tier (config/agreements.php), and the
 * blanks the Word templates left to be filled in by hand — the date, the
 * partner's name and address, the amount and the agreement's duration.
 */
final readonly class PartnershipAgreementTerms
{
    /**
     * @param  list<string>  $contributionClauses
     * @param  list<string>  $amrefRoles
     * @param  list<string>  $partnerRoles
     */
    public function __construct(
        public string $packageLabel,
        public ?string $amount,
        public bool $collaboration,
        public array $contributionClauses,
        public array $amrefRoles,
        public array $partnerRoles,
        public bool $indemnity,
        public CarbonInterface $madeOn,
        public ?CarbonInterface $endsOn,
        public ?int $months,
        public string $edition,
        public ?int $conferenceYear,
    ) {}

    public static function for(Agreement $agreement, Partner $partner, ?SponsorshipPackage $package): self
    {
        $tier = $package?->tier?->value;
        $terms = $tier ? config("agreements.tiers.{$tier}") : null;
        $amount = $package ? self::formatAmount((float) $package->price, $package->currency) : null;

        // A package with no transcribed terms (a custom tier added in the
        // admin) still gets a usable agreement: its listed benefits become
        // Amref's obligations, and paying for it the partner's.
        $terms ??= [
            'collaboration' => false,
            'contribution_clauses' => [],
            'amref_roles' => array_map(
                fn ($benefit) => is_string($benefit) ? $benefit : ($benefit['title'] ?? ''),
                $package?->benefits ?? [],
            ),
            'partner_roles' => $package ? ['Pay for the '.$package->name.' Package (:amount);'] : [],
            'indemnity' => true,
        ];

        $madeOn = ($agreement->generated_at ?? now())->toImmutable()->startOfDay();
        $conference = $partner->conference ?? $package?->conference;

        // The agreement must run until the post-conference report is due, one
        // month after the conference closes. It is stated in whole months, so
        // round up and end exactly that many months after it starts — "eight
        // months ending on" must name the date eight months on.
        $reportDue = $conference?->end_date?->addMonth();
        $months = $reportDue && $reportDue->greaterThan($madeOn)
            ? (int) ceil($madeOn->diffInMonths($reportDue))
            : null;
        $endsOn = $months ? $madeOn->addMonths($months) : null;

        return new self(
            packageLabel: $package?->tier?->label() ?? $package?->name ?? '',
            amount: $amount,
            collaboration: $terms['collaboration'],
            contributionClauses: $terms['contribution_clauses'],
            amrefRoles: array_values(array_filter($terms['amref_roles'])),
            partnerRoles: array_map(
                fn (string $role) => str_replace(':amount', $amount ?? '', $role),
                $terms['partner_roles'],
            ),
            indemnity: $terms['indemnity'],
            madeOn: $madeOn,
            endsOn: $endsOn,
            months: $months,
            edition: config('agreements.edition'),
            conferenceYear: $conference?->year,
        );
    }

    /** "twelve (12)" — how the templates write the duration. */
    public function monthsInWords(): ?string
    {
        return $this->months ? Number::spell($this->months).' ('.$this->months.')' : null;
    }

    /** "$200,000", as the templates write it; other currencies by code. */
    private static function formatAmount(float $price, ?string $currency): string
    {
        $figure = number_format($price, fmod($price, 1.0) === 0.0 ? 0 : 2);

        return ($currency ?? 'USD') === 'USD' ? '$'.$figure : $currency.' '.$figure;
    }
}
