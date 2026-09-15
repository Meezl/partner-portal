<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @include('pdf.partials.brand-styles')
        .invoice-title { text-align: center; font-size: 22px; font-weight: 700; margin: 0 0 10px; color: #211f1f; letter-spacing: 0.04em; }
        table.meta, table.customer, table.items, table.payment { width: 100%; border-collapse: collapse; }
        table.meta td { padding: 2px 0; vertical-align: top; font-size: 10pt; }
        table.meta td.label { width: 36%; color: #1d1d1d; }
        table.meta td.value { font-weight: 700; }
        table.customer { margin-top: 10px; }
        table.customer td { padding: 3px 0; vertical-align: bottom; font-size: 10pt; }
        table.customer td.label { width: 21%; white-space: nowrap; }
        table.customer td.line { border-bottom: 1px dotted #9d948c; color: #433f3b; padding-left: 10px; }
        .invoice-keyline { margin-top: 6px; }
        .invoice-keyline td { padding: 5px 0 3px; font-size: 10pt; }
        .invoice-keyline .label { font-weight: 700; width: 24%; }
        table.items { margin-top: 8px; font-size: 10pt; }
        table.items th, table.items td { border: 1px solid #575350; padding: 5px 8px; }
        table.items th { text-align: left; font-size: 10pt; background: transparent; }
        table.items .qty { width: 6%; text-align: center; }
        table.items .price, table.items .total { width: 22%; text-align: right; }
        table.items .description { width: 50%; }
        table.items .summary-label { text-align: right; font-weight: 700; }
        table.items .summary-value { text-align: right; font-weight: 700; }
        .payment-block { margin-top: 10px; }
        .payment-block td { padding: 2px 0; vertical-align: top; font-size: 10pt; }
        .payment-block .left-label { width: 14%; }
        .payment-block .bank-label { width: 25%; }
        .payment-block .bank-value strong { font-weight: 700; }
        .muted-link { color: #0060aa; text-decoration: underline; }
    </style>
</head>
<body>
    @php
        $conference = $partner->conference;
        $eventName = $invoice->additional_options['event_name'] ?? $conference?->name ?? 'AHAIC';
        $eventDates = $invoice->additional_options['event_dates']
            ?? $conference?->dateRange()
            ?? 'To be confirmed';
        $organizationLabel = $partner->organization_name ?: $partner->contact_person;
    @endphp

    @include('pdf.partials.brand-header')

    <div class="page">

        <div class="content">
            <h1 class="invoice-title">INVOICE</h1>

            <table class="meta">
                <tr>
                    <td class="label">Billing Entity Name:</td>
                    <td class="value">{{ config('ahaic.billing_entity_name') }}</td>
                </tr>
                <tr>
                    <td class="label">Tax Registration Number:</td>
                    <td class="value">{{ config('ahaic.billing_tax_registration_number') }}</td>
                </tr>
                <tr>
                    <td class="label">Billing Project/ Cost Centre Name:</td>
                    <td class="value">{{ config('ahaic.billing_project_cost_centre') }}</td>
                </tr>
                <tr>
                    <td class="label">Event/ Activity billed:</td>
                    <td class="value">{{ $eventName }}</td>
                </tr>
                <tr>
                    <td class="label">Event/ Activity dates:</td>
                    <td class="value">{{ $eventDates }}</td>
                </tr>
                <tr>
                    <td class="label">Contact:</td>
                    <td class="value">{{ config('ahaic.billing_contact_name') }}</td>
                </tr>
                <tr>
                    <td class="label">Contact Email:</td>
                    <td class="value"><span class="muted-link">{{ config('ahaic.billing_contact_email') }}</span></td>
                </tr>
            </table>

            <table class="customer">
                <tr>
                    <td class="label">Customer Name:</td>
                    <td class="line">{{ $organizationLabel }}</td>
                </tr>
                <tr>
                    <td class="label">Contact Person:</td>
                    <td class="line">{{ $partner->contact_person }}</td>
                </tr>
                <tr>
                    <td class="label">Contact Person Title:</td>
                    <td class="line">{{ $invoice->additional_options['package_name'] ?? 'Partner Representative' }}</td>
                </tr>
                <tr>
                    <td class="label">Email Address:</td>
                    <td class="line">{{ $partner->email }}</td>
                </tr>
            </table>

            <table class="invoice-keyline">
                <tr>
                    <td class="label">Invoice Date:</td>
                    <td>{{ $invoice->created_at->format('F j, Y') }}</td>
                    <td class="label">Invoice Number:</td>
                    <td>{{ $invoice->invoice_number }}</td>
                </tr>
                <tr>
                    <td class="label">Customer Code:</td>
                    <td>{{ $invoice->customer_code ?? 'Pending' }}</td>
                    <td class="label">Due Date:</td>
                    <td>{{ $invoice->due_date->format('F j, Y') }}</td>
                </tr>
            </table>

            <table class="items">
                <thead>
                    <tr>
                        <th class="qty">Qty</th>
                        <th class="description">Description</th>
                        <th class="price">Unit Price ({{ $invoice->currency }})</th>
                        <th class="total">Total Amount ({{ $invoice->currency }})</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="qty">1</td>
                        <td class="description">
                            <strong>{{ $invoice->additional_options['package_name'] ?? 'Sponsorship Package' }}</strong>
                            @if(!empty($invoice->additional_options['package_tier']))
                                <br>{{ ucfirst($invoice->additional_options['package_tier']) }} sponsorship package
                            @endif
                            @if($invoice->benefits_summary)
                                <br>Benefits:
                                {{ collect($invoice->benefits_summary)
                                    ->map(fn ($benefit) => is_string($benefit) ? $benefit : ($benefit['title'] ?? 'Benefit'))
                                    ->implode(', ') }}
                            @endif
                            @if(!empty($invoice->additional_options['exhibition_space']))
                                <br>Exhibition Space: {{ $invoice->additional_options['exhibition_space'] }}
                            @endif
                        </td>
                        <td class="price">{{ number_format($invoice->amount, 2) }}</td>
                        <td class="total">{{ number_format($invoice->amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="summary-label">Sub total</td>
                        <td class="summary-value">{{ number_format($invoice->amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="summary-label">VAT/ Applicable Taxes</td>
                        <td class="summary-value">0.00</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="summary-label">Total</td>
                        <td class="summary-value">{{ number_format($invoice->amount, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            @if($invoice->bank_details)
            <div class="payment-block">
                <table class="payment">
                    <tr>
                        <td class="left-label">Payable to:</td>
                        <td class="bank-label">Bank Name:</td>
                        <td class="bank-value"><strong>{{ $invoice->bank_details['bank_name'] ?? '' }}</strong></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="bank-label">Account Name:</td>
                        <td class="bank-value"><strong>{{ $invoice->bank_details['account_name'] ?? '' }}</strong></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="bank-label">Account Number:</td>
                        <td class="bank-value">{{ $invoice->bank_details['account_number'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="bank-label">Account Currency:</td>
                        <td class="bank-value"><strong>{{ $invoice->bank_details['currency'] ?? $invoice->currency }}</strong></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="bank-label">Branch:</td>
                        <td class="bank-value">{{ $invoice->bank_details['branch'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="bank-label">Swift Code:</td>
                        <td class="bank-value"><strong>{{ $invoice->bank_details['swift_code'] ?? '' }}</strong></td>
                    </tr>
                </table>
            </div>
            @endif

        </div>
    </div>

    @include('pdf.partials.brand-footer', ['year' => $conference?->year, 'note' => 'Invoice '.$invoice->invoice_number])
</body>
</html>
