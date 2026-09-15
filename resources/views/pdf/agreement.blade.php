<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $terms->packageLabel }} Partnership Agreement — {{ $partner->organization_name }}</title>
    <style>
        @include('pdf.partials.brand-styles')
        body { font-size: 9.5pt; line-height: 1.45; }
        .title { text-align: center; font-size: 15pt; font-weight: 700; letter-spacing: 0.04em; margin: 0 0 2px; }
        .subtitle { text-align: center; color: #255325; font-weight: 700; margin: 0 0 14px; }
        p { margin: 0 0 7px; text-align: justify; }
        .whereas { font-weight: 700; text-decoration: underline; margin-top: 8px; }
        .fill { font-weight: 700; }
        /* Hand-numbered clauses: the number hangs in the indent, the text wraps under itself. */
        .clause { position: relative; margin: 0 0 5px; text-align: justify; }
        .clause .num { position: absolute; left: 0; top: 0; }
        .level-0 { padding-left: 26px; margin-top: 10px; font-weight: 700; page-break-after: avoid; }
        .level-1 { padding-left: 58px; }
        .level-1 .num { left: 26px; }
        .level-1.heading { font-weight: 700; page-break-after: avoid; }
        .level-2 { padding-left: 96px; }
        .level-2 .num { left: 58px; }
        .recital { padding-left: 46px; }
        .recital .num { left: 26px; }
        .witness { margin-top: 16px; page-break-inside: avoid; }
        table.signatures { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.signatures td { width: 50%; vertical-align: top; padding: 0 16px 0 0; }
        table.signatures .party { font-weight: 700; padding-bottom: 6px; }
        table.signatures .row { padding: 7px 16px 0 0; }
        table.signatures .line { display: inline-block; width: 62%; border-bottom: 1px solid #221f1f; }
        table.signatures .signed { color: #255325; font-weight: 700; }
        .witnesses-label { font-weight: 700; margin: 16px 0 0; }
        .signature-meta { margin-top: 6px; font-size: 8.5pt; color: #255325; }
    </style>
</head>
<body>
    @php
        $conference = $partner->conference ?? $package?->conference;
        $partnerName = $partner->organization_name ?: $partner->contact_person;
        $partnerAddress = $partner->physical_address ?: $partner->billing_address;
        $dots = '……………………';
        $digital = $agreement->signed_method === 'digital' && $agreement->signed_by_name;

        // Sections are numbered as they are rendered, so a tier without the
        // indemnity clause (CSO, Exhibitor) ends at 11 without a gap.
        $sections = [
            [
                'title' => $terms->collaboration ? 'PARTNER CONTRIBUTION AND COLLABORATION' : 'PARTNER CONTRIBUTION',
                'clauses' => [
                    'The Partner agrees to pay to the Amref a total amount of '.($terms->amount ?? $dots).' (the “Partner Contribution”) to support the AHAIC (“the Conference”).',
                    ...$terms->contributionClauses,
                ],
            ],
            [
                'title' => 'PAYMENTS',
                'clauses' => ['Upon execution of this Agreement by both Parties, Amref shall submit to the Partner an invoice for the Partner Contribution which shall be payable upon receipt and acceptance of the duly issued invoice by Partner.'],
            ],
            [
                'title' => 'ROLES OF THE PARTIES',
                'groups' => [
                    'Roles of Amref' => $terms->amrefRoles,
                    'Roles of the Partner' => $terms->partnerRoles,
                ],
            ],
            [
                'title' => 'DURATION OF THE AGREEMENT',
                'clauses' => [
                    'This Agreement shall come into force on '.$terms->madeOn->format('F j, Y')
                        .' and shall continue for '.($terms->monthsInWords() ?? $dots).' months ending on '
                        .($terms->endsOn?->format('F j, Y') ?? $dots)
                        .' unless otherwise renewed by mutual agreement of the parties effected in writing. A report shall be prepared and shared within one month following the conclusion of the conference.',
                ],
            ],
            [
                'title' => 'TERMINATION',
                'clauses' => ['Either Party to this agreement may terminate the agreement by giving notice of 1 (One) month to the other Party. Either Party may terminate this Agreement at any time for a material breach of the Agreement by the other Party by giving written notice of immediate termination to the other Party. Any provision, which by its intent or content is meant to have validity beyond expiry or termination of this Agreement, shall survive the expiry or termination of this Agreement.'],
            ],
            [
                'title' => 'GOVERNING LAW',
                'clauses' => ['This agreement shall be governed by '.config('agreements.governing_law').'.'],
            ],
            [
                'title' => 'AMENDMENTS',
                'clauses' => ['No change / modification of or addition to the agreement shall be valid unless set forth in written documents signed by the Parties.'],
            ],
            [
                'title' => 'CONFIDENTIALITY AND DATA PROTECTION',
                'clauses' => ['All the Parties agree to hold in strict confidence and not to disclose or transfer, directly or indirectly confidential information, except in so far as it is expressly permitted under this agreement or if agreed in writing by the Parties. Both Parties shall comply with all requirements under applicable laws which may apply to them in relation to the processing of personal data in connection with this Agreement.'],
            ],
            [
                'title' => 'INTELLECTUAL PROPERTY',
                'clauses' => ['Each Party acknowledges that each of the other Parties’ intellectual property is the sole and exclusive property of that other Party and nothing in this agreement affects the ownership or right to use its own intellectual property.'],
                'groups' => [
                    'For purposes of fulfilling the objectives of this agreement;' => ['The Partner hereby grants Amref a non-exclusive limited license to use, display, and reproduce its logos, trademarks, service marks, and trade names (each, a "Partner Trademark") only in connection with the promotion and advertisement of the Event and any listing of the partners of the Event during the Term.'],
                ],
                'plainGroups' => true,
            ],
            [
                'title' => 'ARBITRATION',
                'clauses' => ['In the event of any dispute arising between the parties herein, touching on the interpretation of any clause in this agreement or the rights and liabilities of the parties hereto, the parties shall strive to resolve the issue amicably. Failing amicable settlement, the dispute shall within 14 days shall be submitted to the Chartered Institute of Arbitrators (CIArb) and settled by final and binding arbitration in accordance with the CIArb Arbitration Rules.'],
            ],
            [
                'title' => 'FORCE MAJEURE',
                'clauses' => ['Failure of either party to perform its obligations under this Agreement shall not subject such party to any liability to the other if such failure is caused by acts such as, but not limited to, acts of God, fire, explosion, flood, drought, war, riot, sabotage, embargo, strikes, or by any other cause beyond the reasonable control of the parties.'],
            ],
        ];

        if ($terms->indemnity) {
            $sections[] = [
                'title' => 'INDEMNITY',
                'clauses' => ['Each party shall mutually indemnify, and keep indemnified, the other party against all claims, liabilities, costs, expenses or demands or damages brought against either party arising out of or in connection with the performance or non – performance by either party or third parties instructed by it, of services authorized by them. This indemnity shall survive termination of any contractual or other arrangements the parties herein.'],
            ];
        }

        $recitals = [
            'Amref organizes Africa Health Agenda International Conference (AHAIC) on a biennial basis targeting international stakeholders to discuss challenges and opportunities of improving health in Africa and shall hold the '.$terms->edition.' edition in '.($terms->conferenceYear ?? $dots).';',
            'The Partner wishes to provide financial support for certain activities of the Organisation and specifically the financial commitment towards the Africa Health Agenda International Conference (AHAIC) in the form of '.(preg_match('/^[AEIOU]/i', $terms->packageLabel) ? 'an ' : 'a ').$terms->packageLabel.' Partnership Package;',
            'Amref and the Partner are desirous of entering into this partnership agreement in order to influence policymakers and donor priorities in order to advance Universal Health Coverage (UHC) in Africa;',
            'This Agreement constitutes a binding agreement and entails all the obligations in relations to the Partnership.',
        ];
    @endphp

    @include('pdf.partials.brand-header')

    <div class="content">
        <h1 class="title">PARTNERSHIP AGREEMENT</h1>
        <p class="subtitle">{{ $terms->packageLabel }} Partnership Package{{ $conference ? ' · '.$conference->name : '' }}</p>

        <p>
            THIS PARTNERSHIP AGREEMENT (hereinafter referred to as “the Agreement”) is made this
            <span class="fill">{{ $terms->madeOn->format('jS') }}</span> day of <span class="fill">{{ $terms->madeOn->format('F Y') }}</span>
            by and BETWEEN <strong>Amref Health Africa</strong> established for humanitarian purposes whose address is {{ config('agreements.amref_address') }}
            (herein after referred to as <strong>“Amref”</strong> which expression includes its successors in title and permitted assigns)
            of the one part and <span class="fill">{{ $partnerName }}</span> whose address is
            <span class="fill">{{ $partnerAddress ?: $dots }}</span>,
            (herein after referred to as <strong>“the Partner”</strong> which expression shall include its successors in title and permitted assigns).
            Both Amref and the Partner are hereinafter referred to as "individually as <strong>Party</strong>" or "collectively as <strong>Parties</strong>").
        </p>

        <p class="whereas">WHEREAS:</p>
        @foreach($recitals as $i => $recital)
            <div class="clause recital"><span class="num">{{ chr(97 + $i) }}.</span>{{ $recital }}</div>
        @endforeach

        <p style="margin-top: 8px;">
            <strong>NOW, THEREFORE,</strong> in consideration of the foregoing recitals, which are hereby incorporated into this Agreement as an integral part hereof, and the mutual covenants and agreements set forth herein, and for other good and valuable consideration, the receipt and sufficiency of which is hereby acknowledged, Amref and the Partner, intending to be legally bound, hereby agree as follows:
        </p>

        @foreach($sections as $s => $section)
            @php $n = $s + 1; $sub = 0; @endphp
            <div class="clause level-0"><span class="num">{{ $n }}.</span>{{ $section['title'] }}</div>

            @foreach($section['clauses'] ?? [] as $clause)
                <div class="clause level-1"><span class="num">{{ $n }}.{{ ++$sub }}</span>{{ $clause }}</div>
            @endforeach

            @foreach($section['groups'] ?? [] as $heading => $items)
                @php $g = ++$sub; @endphp
                <div class="clause level-1 {{ empty($section['plainGroups']) ? 'heading' : '' }}"><span class="num">{{ $n }}.{{ $g }}</span>{{ $heading }}</div>
                @foreach($items as $j => $item)
                    <div class="clause level-2"><span class="num">{{ $n }}.{{ $g }}.{{ $j + 1 }}</span>{{ $item }}</div>
                @endforeach
            @endforeach
        @endforeach

        <div class="witness">
            <p>IN WITNESS WHEREOF the Parties have hereto set their respective signs on the day and year herein before written.</p>

            <table class="signatures">
                <tr>
                    <td class="party">For Amref Health Africa</td>
                    <td class="party">For the Partner ({{ $partnerName }})</td>
                </tr>
                @foreach(['Name', 'Title', 'Signature', 'Date'] as $field)
                    <tr>
                        <td class="row">{{ $field }}: <span class="line">&nbsp;</span></td>
                        <td class="row">
                            {{ $field }}:
                            @if($digital && $field === 'Name')
                                <span class="signed">{{ $agreement->signed_by_name }}</span>
                            @elseif($digital && $field === 'Signature')
                                <span class="signed">Signed digitally</span>
                            @elseif($digital && $field === 'Date')
                                <span class="signed">{{ $agreement->signed_at?->format('F j, Y') }}</span>
                            @else
                                <span class="line">&nbsp;</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>

            @if($digital)
                <p class="signature-meta">Digitally signed by {{ $agreement->signed_by_name }} in the AHAIC partner portal on {{ $agreement->signed_at?->format('F j, Y \a\t H:i T') }}.</p>
            @elseif($agreement->signed_method === 'upload')
                <p class="signature-meta">Signed copy uploaded on {{ $agreement->signed_at?->format('F j, Y') }}.</p>
            @endif

            <p class="witnesses-label">Witnesses:</p>
            <table class="signatures">
                @foreach(['Name', 'Title', 'Signature', 'Date'] as $field)
                    <tr>
                        <td class="row">{{ $field }}: <span class="line">&nbsp;</span></td>
                        <td class="row">{{ $field }}: <span class="line">&nbsp;</span></td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>

    @include('pdf.partials.brand-footer', [
        'year' => $terms->conferenceYear,
        'note' => $terms->packageLabel.' Partnership Agreement #'.str_pad($agreement->id, 6, '0', STR_PAD_LEFT),
    ])
</body>
</html>
