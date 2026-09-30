<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The media contact belongs with the partner's other contacts, not buried in
 * the branding form, so Communications & Branding is purely what the partner
 * needs and the assets they supply.
 *
 * Existing media contacts are copied across as a `media_lead` partner contact
 * before the columns go, so nothing a partner has already entered is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('branding_requirements')
            ->select('partner_id', 'media_contact_name', 'media_contact_email', 'media_contact_phone')
            ->where(fn ($query) => $query
                ->whereNotNull('media_contact_name')
                ->orWhereNotNull('media_contact_email'))
            ->get();

        foreach ($existing as $branding) {
            $alreadyThere = DB::table('partner_contacts')
                ->where('partner_id', $branding->partner_id)
                ->where('role', 'media_lead')
                ->exists();

            if ($alreadyThere) {
                continue;
            }

            DB::table('partner_contacts')->insert([
                'partner_id' => $branding->partner_id,
                'name' => $branding->media_contact_name ?: 'Media contact',
                'email' => $branding->media_contact_email ?: '',
                'phone' => $branding->media_contact_phone,
                'role' => 'media_lead',
                'designation' => null,
                'organization' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('branding_requirements', function (Blueprint $table) {
            $table->dropColumn(['media_contact_name', 'media_contact_email', 'media_contact_phone']);
        });
    }

    public function down(): void
    {
        Schema::table('branding_requirements', function (Blueprint $table) {
            $table->string('media_contact_name')->nullable()->after('comms_checklist');
            $table->string('media_contact_email')->nullable()->after('media_contact_name');
            $table->string('media_contact_phone')->nullable()->after('media_contact_email');
        });

        $contacts = DB::table('partner_contacts')->where('role', 'media_lead')->get();

        foreach ($contacts as $contact) {
            DB::table('branding_requirements')
                ->where('partner_id', $contact->partner_id)
                ->update([
                    'media_contact_name' => $contact->name,
                    'media_contact_email' => $contact->email ?: null,
                    'media_contact_phone' => $contact->phone,
                ]);
        }
    }
};
