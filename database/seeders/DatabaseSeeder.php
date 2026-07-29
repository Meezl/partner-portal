<?php

namespace Database\Seeders;

use App\Enums\PackageTier;
use App\Enums\UserRole;
use App\Models\Conference;
use App\Models\Room;
use App\Models\SponsorshipPackage;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::factory()->create([
            'name' => 'AHAIC Admin',
            'email' => 'admin@ahaic.org',
            'role' => UserRole::SuperAdmin,
        ]);

        // Create internal team users
        User::factory()->create([
            'name' => 'Finance Manager',
            'email' => 'finance@ahaic.org',
            'role' => UserRole::Finance,
        ]);

        User::factory()->create([
            'name' => 'Programme Lead (GHS)',
            'email' => 'programme@ahaic.org',
            'role' => UserRole::Programme,
        ]);

        User::factory()->create([
            'name' => 'PCO Manager',
            'email' => 'pco@ahaic.org',
            'role' => UserRole::Pco,
        ]);

        User::factory()->create([
            'name' => 'Communications Lead',
            'email' => 'comms@ahaic.org',
            'role' => UserRole::Communications,
        ]);

        User::factory()->create([
            'name' => 'Partnerships Lead',
            'email' => 'partnerships@ahaic.org',
            'role' => UserRole::Partnerships,
        ]);

        // Create a test partner user
        User::factory()->create([
            'name' => 'Test Partner',
            'email' => 'partner@example.com',
            'role' => UserRole::Partner,
        ]);

        // Create AHAIC 2027 Conference
        $conference = Conference::create([
            'name' => 'Africa Health Agenda International Conference 2027',
            'slug' => 'ahaic-2027',
            'year' => 2027,
            'start_date' => '2027-02-28',
            'end_date' => '2027-03-03',
            'venue' => 'Kigali Convention Centre, Rwanda',
            'description' => 'AHAIC is more than a conference. It connects changemakers from across continents to co-create a better future. Forging ideas, solutions, and commitments to resolve the barriers to quality health in Africa.',
            'registration_deadline' => '2027-01-08',
            'onboarding_deadline' => '2027-02-07',
            'lock_date' => '2027-02-14',
            'status' => 'active',
            'settings' => [
                'theme' => 'Connected for Change',
                'central_email' => 'ahaic@amref.org',
            ],
        ]);

        // Create Sponsorship Packages (from AHAIC 2027 Sponsorship Detailed Packages)
        SponsorshipPackage::create([
            'conference_id' => $conference->id,
            'name' => 'Diamond Partner',
            'slug' => 'diamond-partner',
            'tier' => PackageTier::Diamond,
            'price' => 200000.00,
            'currency' => 'USD',
            'max_partners' => 2,
            'description' => 'The pinnacle partnership with exclusive opening/closing plenary access, maximum thought leadership, dedicated technical support, and premium branding across all conference touchpoints.',
            'thought_leadership' => [
                'Exclusive 5-minute Opening OR Closing Plenary speaking slot',
                'Host 2 partner-led sessions (livestreamed, translated in English & French, with videography)',
                'Opportunity to nominate a keynote speaker (pending program approval)',
                'A dedicated technical lead to support partner-led sessions pre- and during the conference',
                '30-minute live studio facilitated coverage to share reflections on key health issues alongside other thought leaders',
                'Access to one invite-only high-level media roundtable discussion (2 Representatives)',
                '1 speaking spot in a side event',
                'Recognition in final conference report and opening/closing ceremonies',
                'Co-host of the Official Opening Reception',
            ],
            'benefits' => [
                'Invitation for one representative to join the Program Advisory or Content Review Committee',
                'Participation in Women in Global Health Awards nomination/vetting',
                'Access to the VIP Partner Lounge for executives',
                'Dedicated senior partner liaison throughout the conference',
                'Priority seating for senior executives',
                'Participation in Walk for Change',
            ],
            'visibility' => [
                'Premium visibility across plenary screens, digital banners, website, app, signage, and exclusive lanyard branding',
                'Full-page feature in the digital conference program',
                'Half-page ad in digital program',
                'Opportunity to submit editorial content or research pieces to the AHAIC website',
                '1-minute promotional video displayed in designated areas',
                'One official media interview and one opinion piece',
                'Exclusive branding on the Walk for Change T-shirt',
            ],
            'session_slots' => 2,
            'exhibition_space' => 'Priority corner booth (6m × 3m)',
            'complimentary_registrations' => ['vip' => 5, 'standard' => 7, 'total' => 12],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        SponsorshipPackage::create([
            'conference_id' => $conference->id,
            'name' => 'Platinum Partner',
            'slug' => 'platinum-partner',
            'tier' => PackageTier::Platinum,
            'price' => 150000.00,
            'currency' => 'USD',
            'max_partners' => 3,
            'description' => 'Premier thought leadership and visibility partnership with high-level plenary speaking, hosted side events, and prominent branding across all conference platforms.',
            'thought_leadership' => [
                'One speaking opportunity in a high-level plenary or flagship session',
                'Host 1 partner-led session (with translation support)',
                'Convene one hosted side event (breakfast, reception, or closed dialogue)',
                '1 speaking spot in a side event',
                'Access to one invite-only high-level media roundtable discussion (2 Representatives)',
                'Recognition in the final conference report and during opening and closing ceremonies',
            ],
            'benefits' => [
                'Access to the Partner & VIP Lounge and official networking receptions',
                'Participation in Walk for Change',
            ],
            'visibility' => [
                'Prominent branding across plenary screens, conference website, app, and venue signage',
                'Opportunity to submit editorial content or research pieces to the AHAIC website',
                'On-site video interview or one co-authored opinion piece',
                'Logo inclusion in official conference materials and digital program',
                'Half-page ad in digital program',
                'Inclusion in conference social media highlights',
            ],
            'session_slots' => 1,
            'exhibition_space' => 'Priority corner booth (5m × 3m)',
            'complimentary_registrations' => ['vip' => 5, 'standard' => 5, 'total' => 10],
            'is_active' => true,
            'sort_order' => 2,
        ]);

        SponsorshipPackage::create([
            'conference_id' => $conference->id,
            'name' => 'Gold Partner',
            'slug' => 'gold-partner',
            'tier' => PackageTier::Gold,
            'price' => 100000.00,
            'currency' => 'USD',
            'max_partners' => 5,
            'description' => 'Strategic partnership with dedicated session hosting, media roundtable access, and strong visibility across conference materials and digital platforms.',
            'thought_leadership' => [
                'Host 1 partner-led session',
                '1 speaking spot in a side event',
                'Access to one invite-only high-level media roundtable discussion (1 Representative)',
                'Recognition in the final conference report and during opening and closing ceremonies',
            ],
            'benefits' => [
                'Access to partner lounge and networking receptions',
                'Participation in Walk for Change',
            ],
            'visibility' => [
                'Logo featured in plenary and digital program',
                'Half-page ad in digital program',
                'General social media visibility',
            ],
            'session_slots' => 1,
            'exhibition_space' => 'Standard booth (3m × 3m)',
            'complimentary_registrations' => ['vip' => 3, 'standard' => 5, 'total' => 8],
            'is_active' => true,
            'sort_order' => 3,
        ]);

        SponsorshipPackage::create([
            'conference_id' => $conference->id,
            'name' => 'Silver Partner',
            'slug' => 'silver-partner',
            'tier' => PackageTier::Silver,
            'price' => 50000.00,
            'currency' => 'USD',
            'max_partners' => 10,
            'description' => 'Targeted partnership for corporates and foundations with session hosting, exhibition presence, and recognition across conference platforms.',
            'thought_leadership' => [
                'Host 1 session (Corporate & Foundations)',
            ],
            'benefits' => [
                'Access to partner lounge and networking receptions',
                'Participation in Walk for Change',
            ],
            'visibility' => [
                'Recognition on the conference website, digital program, and event signage',
            ],
            'session_slots' => 1,
            'exhibition_space' => 'Standard booth (3m × 3m)',
            'complimentary_registrations' => ['vip' => 0, 'standard' => 5, 'total' => 5],
            'is_active' => true,
            'sort_order' => 4,
        ]);

        SponsorshipPackage::create([
            'conference_id' => $conference->id,
            'name' => 'CSO Partner',
            'slug' => 'cso-partner',
            'tier' => PackageTier::Cso,
            'price' => 25000.00,
            'currency' => 'USD',
            'max_partners' => 20,
            'description' => 'Civil Society Organization partnership designed for NGOs and community-based organizations to co-host sessions, showcase impact, and connect with the AHAIC community.',
            'thought_leadership' => [
                'Opportunity to co-host a parallel session with another CSO partner',
            ],
            'benefits' => [
                'Access to partner lounge and networking receptions',
                'Participation in Walk for Change',
            ],
            'visibility' => [
                'Logo featured on selected conference materials',
                'Social media visibility during conference week',
            ],
            'session_slots' => 1,
            'exhibition_space' => 'Standard exhibition booth (3m × 3m)',
            'complimentary_registrations' => ['vip' => 0, 'standard' => 3, 'total' => 3],
            'is_active' => true,
            'sort_order' => 5,
        ]);

        SponsorshipPackage::create([
            'conference_id' => $conference->id,
            'name' => 'Exhibitor',
            'slug' => 'exhibitor',
            'tier' => PackageTier::Exhibitor,
            'price' => 7500.00,
            'currency' => 'USD',
            'max_partners' => 30,
            'description' => 'Exhibition-focused partnership for organizations looking to showcase products, services, and innovations at Africa\'s leading health conference.',
            'thought_leadership' => [],
            'benefits' => [
                'Access to partner lounge and networking receptions (where applicable)',
                'Participation in Walk for Change',
            ],
            'visibility' => [
                'Logo featured on the conference website and digital program',
            ],
            'session_slots' => 0,
            'exhibition_space' => 'Standard booth (3m × 3m)',
            'complimentary_registrations' => ['vip' => 0, 'standard' => 2, 'total' => 2],
            'is_active' => true,
            'sort_order' => 6,
        ]);

        // Rooms — verbatim from the "Room Details" and "Allocation matrix tables" tabs
        // of the Session Slots & Booths Scheduling Matrix workbook.
        // capacity is the theatre-style figure; round-table capacity stored under equipment.round_capacity.
        $rooms = [
            ['name' => 'Auditorium', 'building' => 'KCC', 'floor' => 'Plenary',  'capacity' => 1200, 'format_suitability' => ['plenary', 'panel'],                  'equipment' => ['purpose' => 'Plenary hall',         'round_capacity' => null, 'translation_booths' => 3, 'livestream' => true]],
            ['name' => 'MH1',        'building' => 'KCC', 'floor' => 'Main',     'capacity' => 500,  'format_suitability' => ['panel', 'workshop', 'roundtable'],   'equipment' => ['purpose' => 'Parallel Sessions',    'round_capacity' => 100, 'note' => 'Big Room']],
            ['name' => 'MH2',        'building' => 'KCC', 'floor' => 'Main',     'capacity' => 500,  'format_suitability' => ['panel', 'workshop', 'roundtable'],   'equipment' => ['purpose' => 'Parallel Sessions',    'round_capacity' => 100, 'note' => 'Big Room']],
            ['name' => 'MH3',        'building' => 'KCC', 'floor' => 'Main',     'capacity' => 500,  'format_suitability' => ['panel', 'workshop', 'roundtable'],   'equipment' => ['purpose' => 'Parallel Sessions',    'round_capacity' => 100, 'note' => 'Big Room']],
            ['name' => 'MH4',        'building' => 'KCC', 'floor' => 'Main',     'capacity' => 600,  'format_suitability' => ['panel', 'workshop', 'roundtable'],   'equipment' => ['purpose' => 'Parallel Sessions',    'round_capacity' => 100, 'note' => 'Big Room']],
            ['name' => 'AD10',       'building' => 'KCC', 'floor' => 'AD',       'capacity' => 200,  'format_suitability' => ['panel', 'workshop', 'roundtable'],   'equipment' => ['purpose' => 'Parallel Sessions',    'round_capacity' => 80,  'u_shape_capacity' => 50, 'note' => 'Medium room']],
            ['name' => 'AD11',       'building' => 'KCC', 'floor' => 'AD',       'capacity' => 70,   'format_suitability' => ['roundtable', 'side_event'],          'equipment' => ['purpose' => 'Parallel Sessions',    'round_capacity' => 40,  'note' => 'Small room']],
            ['name' => 'AD12',       'building' => 'KCC', 'floor' => 'AD',       'capacity' => 270,  'format_suitability' => ['panel', 'workshop', 'roundtable'],   'equipment' => ['purpose' => 'Parallel Sessions',    'round_capacity' => 130, 'note' => 'Medium room']],
            ['name' => 'Mezzanine',  'building' => 'KCC', 'floor' => 'Mezzanine','capacity' => 70,   'format_suitability' => ['workshop'],                          'equipment' => ['purpose' => 'Media',                'note' => 'Classroom — media room', 'is_assignable' => false]],
            ['name' => 'AD1',        'building' => 'KCC', 'floor' => 'AD',       'capacity' => 70,   'format_suitability' => ['side_event'],                        'equipment' => ['purpose' => 'VIP / Speaker Lounge', 'note' => 'Lounge set up', 'is_assignable' => false]],
            ['name' => 'AD3',        'building' => 'KCC', 'floor' => 'AD',       'capacity' => 30,   'format_suitability' => ['side_event'],                        'equipment' => ['purpose' => 'VIP Lounge / Speaker Holding', 'note' => 'Lounge set up', 'is_assignable' => false]],
            ['name' => 'AD4',        'building' => 'KCC', 'floor' => 'AD',       'capacity' => 30,   'format_suitability' => ['roundtable', 'side_event'],          'equipment' => ['purpose' => 'Vacant',               'u_shape_capacity' => 20, 'note' => 'No sound']],
            ['name' => 'AD5',        'building' => 'KCC', 'floor' => 'AD',       'capacity' => 12,   'format_suitability' => ['roundtable', 'side_event'],          'equipment' => ['purpose' => 'Ministry of Health',   'note' => 'Fixed boardroom — 12', 'is_assignable' => false]],
            ['name' => 'AD6',        'building' => 'KCC', 'floor' => 'AD',       'capacity' => 12,   'format_suitability' => ['side_event'],                        'equipment' => ['purpose' => 'Speaker Briefing',     'note' => 'Fixed boardroom — 12', 'is_assignable' => false]],
            ['name' => 'AD7',        'building' => 'KCC', 'floor' => 'AD',       'capacity' => 24,   'format_suitability' => ['side_event'],                        'equipment' => ['purpose' => 'Secretariat / Rapporteurs / Business Centre', 'note' => 'Fixed boardroom — 24', 'is_assignable' => false]],
            ['name' => 'AD8',        'building' => 'KCC', 'floor' => 'AD',       'capacity' => 10,   'format_suitability' => ['roundtable', 'side_event'],          'equipment' => ['purpose' => 'Vacant',               'note' => 'Fixed boardroom — 10']],
            ['name' => 'AD9',        'building' => 'KCC', 'floor' => 'AD',       'capacity' => 12,   'format_suitability' => ['roundtable', 'side_event'],          'equipment' => ['purpose' => 'Vacant',               'note' => 'Fixed boardroom — 12']],
            ['name' => 'MR1',        'building' => 'KCC', 'floor' => 'MR',       'capacity' => 12,   'format_suitability' => ['side_event'],                        'equipment' => ['purpose' => 'Production / Edelman', 'note' => 'Boardroom — 12', 'is_assignable' => false]],
            ['name' => 'Foyer 1A',   'building' => 'KCC', 'floor' => 'Foyer 1',  'capacity' => 0,    'format_suitability' => ['exhibition'],                        'equipment' => ['purpose' => 'Accreditation + Exhibition', 'is_assignable' => false]],
            ['name' => 'Foyer 1B',   'building' => 'KCC', 'floor' => 'Foyer 1',  'capacity' => 0,    'format_suitability' => ['exhibition'],                        'equipment' => ['purpose' => 'Partner Lounge',       'note' => 'Lounge set up', 'is_assignable' => false]],
            ['name' => 'Foyer 1C',   'building' => 'KCC', 'floor' => 'Foyer 1',  'capacity' => 0,    'format_suitability' => ['exhibition'],                        'equipment' => ['purpose' => 'F&B',                  'is_assignable' => false]],
            ['name' => 'Concourse',  'building' => 'KCC', 'floor' => 'Concourse','capacity' => 0,    'format_suitability' => ['exhibition'],                        'equipment' => ['purpose' => 'Exhibition + F&B + Youth Zone', 'is_assignable' => false]],
        ];

        foreach ($rooms as $room) {
            Room::create(array_merge($room, ['conference_id' => $conference->id]));
        }

        // Time slots — match the four-day AHAIC daily pattern from the matrix workbook
        // (Day 0 arrival/Walk-for-Change, then full Day 1–3 with breakfasts, plenaries, parallels, POPs, receptions).
        $dates = ['2027-02-28', '2027-03-01', '2027-03-02', '2027-03-03'];
        $day0Slots = [
            ['start_time' => '13:00', 'end_time' => '18:00', 'label' => 'Youth Summit',     'slot_type' => 'session'],
            ['start_time' => '14:00', 'end_time' => '15:00', 'label' => 'Press Briefing',   'slot_type' => 'session'],
            ['start_time' => '16:00', 'end_time' => '17:30', 'label' => 'Opening Reception', 'slot_type' => 'networking'],
            ['start_time' => '18:00', 'end_time' => '20:00', 'label' => 'Walk for Change',   'slot_type' => 'networking'],
        ];
        $dayFullSlots = [
            ['start_time' => '08:00', 'end_time' => '09:30', 'label' => 'Breakfast Sessions',   'slot_type' => 'session'],
            ['start_time' => '09:30', 'end_time' => '11:00', 'label' => 'Plenary',               'slot_type' => 'keynote'],
            ['start_time' => '11:00', 'end_time' => '11:30', 'label' => 'Networking Break',      'slot_type' => 'break'],
            ['start_time' => '11:30', 'end_time' => '13:00', 'label' => 'Parallel Sessions AM',  'slot_type' => 'session'],
            ['start_time' => '13:00', 'end_time' => '14:00', 'label' => 'Lunch',                 'slot_type' => 'break'],
            ['start_time' => '14:00', 'end_time' => '15:30', 'label' => 'Parallel Sessions PM',  'slot_type' => 'session'],
            ['start_time' => '15:30', 'end_time' => '16:00', 'label' => 'Networking Break',      'slot_type' => 'break'],
            ['start_time' => '16:00', 'end_time' => '17:30', 'label' => 'Plenary',               'slot_type' => 'keynote'],
            ['start_time' => '18:00', 'end_time' => '19:30', 'label' => 'POP / Reception',       'slot_type' => 'networking'],
        ];
        $day3Slots = [
            ['start_time' => '08:00', 'end_time' => '09:30', 'label' => 'Breakfast Sessions',   'slot_type' => 'session'],
            ['start_time' => '09:30', 'end_time' => '11:00', 'label' => 'Parallel Sessions AM', 'slot_type' => 'session'],
            ['start_time' => '11:00', 'end_time' => '11:30', 'label' => 'Networking Break',     'slot_type' => 'break'],
            ['start_time' => '11:30', 'end_time' => '13:00', 'label' => 'Parallel Sessions',    'slot_type' => 'session'],
            ['start_time' => '13:00', 'end_time' => '14:00', 'label' => 'POP / Lunch',          'slot_type' => 'session'],
            ['start_time' => '14:00', 'end_time' => '16:00', 'label' => 'Closing Plenary',      'slot_type' => 'keynote'],
        ];

        $dayMap = [
            $dates[0] => $day0Slots,
            $dates[1] => $dayFullSlots,
            $dates[2] => $dayFullSlots,
            $dates[3] => $day3Slots,
        ];

        foreach ($dayMap as $date => $slots) {
            foreach ($slots as $slot) {
                TimeSlot::create(array_merge($slot, [
                    'conference_id' => $conference->id,
                    'date' => $date,
                ]));
            }
        }

        // Build the bookable session-slot inventory + booths from the matrix.
        $this->call(SessionSlotSeeder::class);

        // Import the session-lead + rapporteur roster.
        $this->call(RapporteurAndSessionLeadSeeder::class);

        // Demo partner organisations + scheduled sessions for the allocation board.
        $this->call(DemoSessionSeeder::class);
    }
}
