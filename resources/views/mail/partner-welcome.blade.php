{{-- Wording supplied by the partnerships team ("Make Payment Page" brief). --}}
<x-mail::message>
# Dear {{ $organization }} Team,

Greetings from the AHAIC Team.

Thank you for partnering with us and for completing the partnership agreement
process. We are delighted to welcome {{ $organization }} as an official partner
of {{ $conferenceName }}.

{{ $conferenceName }} will take place from {{ $dates }} at {{ $venue }}, under the theme:
"From Dialogue to Delivery: Redesigning Africa's Ecosystem for a Healthy, Sovereign Future."

As we begin preparations for the conference, we kindly request that you complete
your organization's profile on the AHAIC Partner Portal as the next step in the
onboarding process.

## Partner Portal Access

Please log in and complete the required information via the Partner Portal:

<x-mail::button :url="$portalUrl">
Access the AHAIC Partner Portal
</x-mail::button>

The information collected during this phase will enable the AHAIC Team to begin
planning and coordinating your conference participation, visibility, and
partnership benefits.

The portal includes information such as:

- Organization and focal point details
- Partner profile information
- Branding and logo requirements
- Communication and visibility preferences
- Delegate planning information
- Initial partnership requirements

We kindly request that you complete all required fields at your earliest
convenience to facilitate planning and ensure we can effectively support your
engagement.

## What Happens Next?

Once Phase 1 information has been submitted, the AHAIC Team will review your
profile and provide guidance on the next stage of engagement.

Phase 2 will focus on the activation of your partnership benefits and will
include the submission of:

- Session and speaking information
- Speaker profiles and biographies
- Exhibition requirements
- Delegate registrations
- Side events and networking activities
- Additional branding and visibility requirements

In the meantime, you can access the latest conference updates and information on
the AHAIC website: [Visit the {{ $conferenceName }} Website]({{ $websiteUrl }})

Thank you once again for your partnership and commitment to advancing Africa's
health agenda. We look forward to working closely with you and welcoming you to
Kigali in {{ $arrivalMonth }}.

Warm regards,<br>
The {{ $conferenceName }} Team<br>
Africa Health Agenda International Conference (AHAIC)<br>
Amref Health Africa<br>
{{ $contactEmail }}
</x-mail::message>
