<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>AHAIC 2027 Sponsorship Agreement</title>
    <style>
        body { font-family: sans-serif; font-size: 12pt; line-height: 1.5; color: #1a1a1a; margin: 0; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #255325; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { width: 90px; height: 90px; margin-bottom: 12px; }
        .title { color: #255325; font-size: 24pt; margin: 10px 0; }
        .section { margin-bottom: 25px; }
        .section-title { font-weight: bold; color: #255325; font-size: 14pt; border-bottom: 1px solid #d9d0c8; padding-bottom: 5px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #d9d0c8; padding: 10px; text-align: left; }
        th { background-color: #f5f0eb; width: 30%; font-weight: bold; }
        .footer { position: fixed; bottom: 0; width: 100%; font-size: 10pt; text-align: center; border-top: 1px solid #d9d0c8; padding-top: 10px; color: #6b6560; }
        .signatures { margin-top: 50px; display: table; width: 100%; }
        .signature-block { display: table-cell; width: 45%; }
        .signature-line { border-bottom: 1px solid #1a1a1a; margin-top: 40px; margin-bottom: 5px; width: 80%; }
        .signature-meta { margin-top: 12px; font-size: 11pt; color: #255325; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('ahaic-logo.jpg') }}" alt="AHAIC" class="logo">
        <h1 class="title">Sponsorship Agreement</h1>
        <h2>AHAIC 2027</h2>
    </div>

    <div class="content">
        <p>This Sponsorship Agreement is made and entered into as of <strong>{{ $agreement->generated_at->format('F d, Y') }}</strong> by and between Amref Health Africa (organizers of AHAIC 2027) and the Sponsor named below.</p>

        <div class="section">
            <h3 class="section-title">1. Sponsor Information</h3>
            <table>
                <tr><th>Organization Name</th><td>{{ $partner->organization_name }}</td></tr>
                <tr><th>Primary Contact</th><td>{{ $partner->contact_person }}</td></tr>
                <tr><th>Email Address</th><td>{{ $partner->email }}</td></tr>
                <tr><th>Phone Number</th><td>{{ $partner->phone }}</td></tr>
                <tr><th>Physical Address</th><td>{{ $partner->physical_address }}</td></tr>
            </table>
        </div>

        @if($package)
        <div class="section">
            <h3 class="section-title">2. Sponsorship Details</h3>
            <table>
                <tr><th>Package Chosen</th><td>{{ $package->name }} ({{ ucfirst($package->tier->value ?? 'Custom') }})</td></tr>
                <tr><th>Commitment Amount</th><td>{{ $package->currency }} {{ number_format($package->price, 2) }}</td></tr>
            </table>

            <h4>Key Benefits Included:</h4>
            <ul>
                @foreach($package->benefits ?? [] as $benefit)
                    <li>{{ is_string($benefit) ? $benefit : ($benefit['title'] ?? 'Benefit') }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="section">
            <h3 class="section-title">3. Terms & Conditions</h3>
            <p>1. <strong>Payment:</strong> The Sponsor agrees to pay the Commitment Amount in full within 30 days of invoice issuance.</p>
            <p>2. <strong>Cancellation:</strong> Cancellations made before 90 days prior to the event will receive a 50% refund. No refunds will be provided for cancellations made within 90 days of the event.</p>
            <p>3. <strong>Logos/Branding:</strong> The Sponsor must provide branding materials by the agreed deadline to guarantee inclusion in event materials.</p>
        </div>

        <div class="signatures">
            <div class="signature-block">
                <p><strong>For Amref Health Africa:</strong></p>
                <div class="signature-line"></div>
                <p>Name: ______________________</p>
                <p>Date: ______________________</p>
            </div>
            <div class="signature-block">
                <p><strong>For Sponsor ({{ $partner->organization_name }}):</strong></p>
                @if($agreement->signed_method === 'digital' && $agreement->signed_by_name)
                    <div class="signature-line">{{ $agreement->signed_by_name }}</div>
                    <p>Name: {{ $agreement->signed_by_name }}</p>
                    <p>Date: {{ optional($agreement->signed_at)->format('F d, Y') }}</p>
                    <p class="signature-meta">Digitally signed in the AHAIC partner portal.</p>
                @else
                    <div class="signature-line"></div>
                    <p>Name: ______________________</p>
                    <p>Date: ______________________</p>
                    @if($agreement->signed_method === 'upload')
                        <p class="signature-meta">Signed copy uploaded on {{ optional($agreement->signed_at)->format('F d, Y') }}.</p>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <div class="footer">
        AHAIC 2027 Partner Portal - Generated Document #{{ str_pad($agreement->id, 6, '0', STR_PAD_LEFT) }}
    </div>
</body>
</html>
