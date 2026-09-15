<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Visa Invitation Letter — {{ $name }}</title>
    <style>
        @include('pdf.partials.brand-styles')
        .meta p { margin: 0 0 2px; font-weight: 700; }
        .meta { margin-bottom: 12px; }
        .salutation { margin: 0 0 10px; font-weight: 700; }
        .subject { font-weight: 700; text-decoration: underline; margin: 0 0 10px; }
        .body p { margin: 0 0 8px; text-align: justify; }
        .signoff { margin-top: 12px; }
        .signoff img { width: 110px; margin: 4px 0 0; }
    </style>
</head>
<body>
    @php
        $start = $conference->start_date;
        $end = $conference->end_date;
        // "2nd – 5th March, 2027", or across a month boundary "28th February – 3rd March, 2027".
        $dates = $start && $end
            ? ($start->isSameMonth($end)
                ? $start->format('jS').' – '.$end->format('jS F, Y')
                : $start->format('jS F').' – '.$end->format('jS F, Y'))
            : 'dates to be confirmed';
        $contact = config('ahaic.billing_contact_email');
    @endphp

    @include('pdf.partials.brand-header')

    <div class="content">
        <div class="meta">
            <p>{{ now()->format('d/m/Y') }}</p>
            <p>{{ $name }}</p>
            <p>Passport Number: {{ $passportNumber }}</p>
        </div>

        <p class="salutation">Dear {{ $name }},</p>

        <p class="subject">RE: INVITATION TO ATTEND THE AFRICA HEALTH AGENDA INTERNATIONAL CONFERENCE, {{ Str::upper($dates) }}</p>

        <div class="body">
            <p>Greetings from Amref Health Africa! We are pleased to invite you to {{ $conference->name }}, scheduled to take place {{ $dates }}@if($conference->venue) at {{ $conference->venue }}@endif.</p>

            <p>The conference is co-hosted by the Ministry of Health of the Republic of Rwanda, Africa Centres for Disease Control and Prevention (Africa CDC), and the World Health Organization Regional Office for Africa (WHO AFRO). This event will bring together over 2,000 global leaders to engage in forward-thinking discussions, side events, and hallmark gatherings.</p>

            <p>For the past decade, Amref has had the privilege of leading policy-level thought leadership in Africa. We have engaged regional and national leaders, innovators, policymakers, technical experts, civil society, the private sector, academia, and global partners to prioritise an Africa-led health development agenda. We recognise that African solutions are needed to solve African problems, requiring an equitable, multi-stakeholder approach to address the continent's challenges and respond to the needs of our communities.</p>

            <p>The Africa Health Agenda International Conference serves as a critical regional platform to launch key advocacy agendas, from Universal Health Coverage to Climate and Health. We acknowledge that Africa's health agenda is shaped by the priorities, complexities, and challenges of public health delivery and access systems, and solving these requires impactful partnerships.</p>

            <p>The theme of this edition is “Connected for Change: Addressing Socio-Ecological Dynamics of Health.” The conference will provide a crucial platform for leaders in health to engage in high-level discussions, offering insights into the latest health innovations, strategies for improving health systems, and ways to address emerging health challenges.</p>

            <p>If you need any further support to secure your visa, please contact <a href="mailto:{{ $contact }}">{{ $contact }}</a>.</p>
        </div>

        <div class="signoff">
            Yours sincerely,<br>
            <img src="{{ public_path(config('ahaic.visa_signatory.signature')) }}" alt="Signature"><br>
            <strong>{{ config('ahaic.visa_signatory.name') }}</strong><br>
            {{ config('ahaic.visa_signatory.title') }}<br>
            {{ config('ahaic.visa_signatory.organization') }}
        </div>
    </div>

    @include('pdf.partials.brand-footer', ['year' => $conference->year ?? $start?->year])
</body>
</html>
