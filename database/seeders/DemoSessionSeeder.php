<?php

namespace Database\Seeders;

use App\Enums\PartnerStatus;
use App\Enums\SessionFormat;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Partner;
use App\Models\SessionSchedule;
use App\Models\Booth;
use App\Models\SessionSlot;
use App\Models\SponsorshipPackage;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Realistic demo data drawn from the Master Sessions Sheet of the
 * Session Slots & Booths Scheduling Matrix workbook. Populates 8 partner
 * organisations, 15 sessions across all 4 days, claims the matching
 * session slots and creates scheduled `SessionSchedule` rows so the
 * admin's Room Allocation Board renders a real conference agenda.
 */
class DemoSessionSeeder extends Seeder
{
    public function run(): void
    {
        $conference = Conference::orderBy('id')->first();
        if (! $conference) {
            return;
        }

        $exhibitorPackage = SponsorshipPackage::where('conference_id', $conference->id)
            ->orderByRaw("FIELD(tier,'gold','silver','platinum','diamond','cso','exhibitor')")
            ->first();

        $partners = [];
        foreach (self::PARTNERS as $p) {
            $user = User::firstOrCreate(
                ['email' => $p['email']],
                [
                    'name' => $p['contact'],
                    'password' => Hash::make(Str::random(24)),
                    'role' => UserRole::Partner,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $partner = Partner::firstOrCreate(
                ['user_id' => $user->id, 'conference_id' => $conference->id],
                [
                    'organization_name' => $p['name'],
                    'slug' => Str::slug($p['name']),
                    'contact_person' => $p['contact'],
                    'email' => $p['email'],
                    'phone' => '+254700000000',
                    'physical_address' => 'Nairobi, Kenya',
                    'status' => PartnerStatus::Onboarding,
                ],
            );

            if ($exhibitorPackage && ! $partner->packages()->where('sponsorship_package_id', $exhibitorPackage->id)->exists()) {
                $partner->packages()->attach($exhibitorPackage->id, ['confirmed_at' => now()]);
            }

            $partners[$p['key']] = $partner;
        }

        foreach (self::SESSIONS as $s) {
            $partner = $partners[$s['partner']] ?? null;
            if (! $partner) {
                continue;
            }

            $slot = SessionSlot::where('conference_id', $conference->id)
                ->where('slot_code', $s['slot_code'])
                ->first();

            if (! $slot || $slot->claimed_by_session_id !== null) {
                continue;
            }

            // Sessions flagged pending stay as "submitted" without a slot
            // claim or schedule row, so the admin's scheduling dropdown has
            // material to work with.
            $isPending = ! empty($s['pending']);
            $status = $isPending ? SessionStatus::Submitted : SessionStatus::Scheduled;

            $session = ConferenceSession::create([
                'partner_id' => $partner->id,
                'conference_id' => $conference->id,
                'title' => $s['title'],
                'description' => $s['description'] ?? null,
                'format' => $s['format'],
                'organizers' => [$partner->organization_name],
                'target_audience' => $s['audience'] ?? 'Global health leaders',
                'expected_participants' => $s['expected'] ?? 60,
                'is_open' => $s['is_open'] ?? true,
                'special_requirements' => [
                    'av_equipment' => true,
                    'translation' => $s['translation'] ?? false,
                    'seating_type' => $slot->default_format === 'round' ? 'round_tables' : 'theater',
                    'catering' => $slot->slot_category === 'breakfast' || $slot->slot_category === 'reception',
                ],
                'session_slot_id' => $isPending ? null : $slot->id,
                'status' => $status,
                'submitted_at' => now(),
            ]);

            if ($isPending) {
                continue;
            }

            $slot->update(['claimed_by_session_id' => $session->id, 'claimed_at' => now()]);

            $timeSlot = $slot->date && $slot->start_time
                ? TimeSlot::where('conference_id', $conference->id)
                    ->whereDate('date', $slot->date)
                    ->whereTime('start_time', $slot->start_time)
                    ->first()
                : null;

            if ($slot->default_room_id && $timeSlot) {
                SessionSchedule::create([
                    'conference_session_id' => $session->id,
                    'room_id' => $slot->default_room_id,
                    'time_slot_id' => $timeSlot->id,
                    'assigned_by' => null,
                    'status' => 'scheduled',
                ]);
            }
        }

        // Demo booth allocations so the exhibition board renders populated.
        $boothAllocations = [
            ['partner' => 'gates',       'zone' => 'Foyer 1A', 'number' => '1'],
            ['partner' => 'msd',         'zone' => 'Foyer 1A', 'number' => '2'],
            ['partner' => 'roche',       'zone' => 'Foyer 1A', 'number' => '5'],
            ['partner' => 'jj',          'zone' => 'Foyer 1A', 'number' => '8'],
            ['partner' => 'wateraid',    'zone' => 'Foyer 1B', 'number' => '26'],
            ['partner' => 'wellcome',    'zone' => 'Foyer 1B', 'number' => '30'],
            ['partner' => 'serum',       'zone' => 'Foyer 1A', 'number' => '12', 'status' => 'reserved'],
            ['partner' => 'rockefeller', 'zone' => 'Foyer 1B', 'number' => '35', 'status' => 'reserved'],
        ];

        foreach ($boothAllocations as $b) {
            $partner = $partners[$b['partner']] ?? null;
            if (! $partner) {
                continue;
            }

            Booth::where('conference_id', $conference->id)
                ->where('zone', $b['zone'])
                ->where('booth_number', $b['number'])
                ->update([
                    'status' => $b['status'] ?? 'assigned',
                    'partner_id' => $partner->id,
                ]);
        }
    }

    private const PARTNERS = [
        ['key' => 'gates',      'name' => 'Gates Foundation',                'contact' => 'Program Officer',  'email' => 'demo-gates@example.com'],
        ['key' => 'msd',        'name' => 'MSD',                             'contact' => 'Dr. Fungai Mettler', 'email' => 'demo-msd@example.com'],
        ['key' => 'roche',      'name' => 'Roche',                           'contact' => 'Lisa Slater',      'email' => 'demo-roche@example.com'],
        ['key' => 'jj',         'name' => 'Johnson & Johnson',               'contact' => 'Anne Gitimu',      'email' => 'demo-jj@example.com'],
        ['key' => 'wateraid',   'name' => 'WaterAid',                        'contact' => 'Annie Msosa',      'email' => 'demo-wateraid@example.com'],
        ['key' => 'wellcome',   'name' => 'Wellcome Trust',                  'contact' => 'Clare Battle',     'email' => 'demo-wellcome@example.com'],
        ['key' => 'serum',      'name' => 'Serum Institute of India',        'contact' => 'Immunization Lead','email' => 'demo-serum@example.com'],
        ['key' => 'rockefeller','name' => 'Rockefeller Foundation',          'contact' => 'Programme Lead',   'email' => 'demo-rockefeller@example.com'],
    ];

    // Titles + partners lifted from the Master Sessions Sheet.
    private const SESSIONS = [
        // Day 1 breakfasts
        ['slot_code' => 'Breakfast 2', 'partner' => 'serum',   'format' => SessionFormat::Roundtable->value, 'title' => 'Celebrating African Leadership in Immunization', 'expected' => 30],
        ['slot_code' => 'Breakfast 3', 'partner' => 'msd',     'format' => SessionFormat::Roundtable->value, 'title' => 'From Crisis to Care: Cardiometabolic Disease Control in Africa', 'expected' => 30],

        // Day 1 parallels — Track 1 (11:30–13:00)
        ['slot_code' => 'Parallel 1.1', 'partner' => 'gates',     'format' => SessionFormat::Panel->value,     'title' => 'Enhancing Capacity to Conduct Health Research, Development and Innovation in Africa', 'expected' => 50],
        ['slot_code' => 'Parallel 1.4', 'partner' => 'msd',       'format' => SessionFormat::Roundtable->value,'title' => "Maternal Health 2.0: Enhancing Women's Health and Family Wellbeing", 'expected' => 80],
        ['slot_code' => 'Parallel 1.5', 'partner' => 'serum',     'format' => SessionFormat::Roundtable->value,'title' => 'Making History: Vaccine Uptake to Stop Malaria and Meningitis', 'expected' => 50],

        // Day 1 parallels — Track 2 (14:00–15:30)
        ['slot_code' => 'Parallel 2.3', 'partner' => 'wateraid', 'format' => SessionFormat::Panel->value,     'title' => 'The Power of WASH: Improving Maternal Health and Combating Infectious Diseases', 'expected' => 120],
        ['slot_code' => 'Parallel 2.5', 'partner' => 'gates',    'format' => SessionFormat::Roundtable->value,'title' => "Accelerating RMNCH Interventions to Save Mothers and Babies in Sub-Saharan Africa", 'expected' => 60],

        // Day 1 reception
        ['slot_code' => 'Reception 1', 'partner' => 'gates', 'format' => SessionFormat::SideEvent->value, 'title' => 'Official Launch of the Africa Clinical Research Network (ACRN)', 'expected' => 150, 'is_open' => false],

        // Day 2 breakfasts + parallels
        ['slot_code' => 'Breakfast 6',  'partner' => 'wellcome',    'format' => SessionFormat::Roundtable->value,'title' => "Bolstering Africa's Emergency Preparedness and Response", 'expected' => 30, 'is_open' => false],
        ['slot_code' => 'Parallel 3.2', 'partner' => 'rockefeller', 'format' => SessionFormat::Roundtable->value,'title' => 'Climate and Health Nexus: Public Health & Meteorological Agencies', 'expected' => 40],
        ['slot_code' => 'Parallel 3.6', 'partner' => 'roche',       'format' => SessionFormat::Roundtable->value,'title' => 'Bridging Disparities: Advancing Breast Cancer Care for African Women', 'expected' => 60],
        ['slot_code' => 'Parallel 4.2', 'partner' => 'jj',          'format' => SessionFormat::Roundtable->value,'title' => 'Restoring Dignity for Every Woman: Transforming Fistula Care Across Africa', 'expected' => 70],

        // Day 2 POP
        ['slot_code' => 'POP 2', 'partner' => 'rockefeller', 'format' => SessionFormat::SideEvent->value, 'title' => 'Addressing Social Determinants of Health for SRHR', 'expected' => 40],

        // Day 3
        ['slot_code' => 'Parallel 5.2', 'partner' => 'roche',    'format' => SessionFormat::Roundtable->value,'title' => 'Financing Cancer Care in Africa: Changing the Narrative from Cost to Return', 'expected' => 50],
        ['slot_code' => 'Parallel 6.5', 'partner' => 'wellcome', 'format' => SessionFormat::Roundtable->value,'title' => 'An Action Agenda to Transform Health Financing in Africa', 'expected' => 80],

        // ---- Submitted, awaiting room assignment (admin dropdown fodder) ----
        ['pending' => true, 'slot_code' => 'Breakfast 1',  'partner' => 'wateraid',    'format' => SessionFormat::Roundtable->value,'title' => 'Shaping Tomorrow: Empowering Youth for Sustainable Careers in Health', 'expected' => 30],
        ['pending' => true, 'slot_code' => 'Parallel 1.3', 'partner' => 'jj',          'format' => SessionFormat::Panel->value,     'title' => 'Towards Professionalized CHWs: Mobility, the Missing Piece', 'expected' => 60],
        ['pending' => true, 'slot_code' => 'Parallel 2.1', 'partner' => 'wellcome',    'format' => SessionFormat::Roundtable->value,'title' => 'Funders Coalition — Private Convening', 'expected' => 40, 'is_open' => false],
        ['pending' => true, 'slot_code' => 'Parallel 4.5', 'partner' => 'rockefeller', 'format' => SessionFormat::Roundtable->value,'title' => 'Building Resilient Local Systems for Locally-Led Implementation', 'expected' => 50],
        ['pending' => true, 'slot_code' => 'Parallel 5.4', 'partner' => 'msd',         'format' => SessionFormat::Panel->value,     'title' => "Curated Conversations: Self-Sufficiency in Africa's Public Health System", 'expected' => 60],
    ];
}
