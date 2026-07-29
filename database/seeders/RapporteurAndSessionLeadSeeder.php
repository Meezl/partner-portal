<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Roster imported from "Rapporteur names and contacts_2025.xlsx".
 *  - 22 Session Leads
 *  - 35 Rapporteurs
 *
 * Both are created as users so the admin can auto-assign them onto
 * scheduled sessions via ResourceAssignment. Users without a login use a
 * random hashed password; they'll go through the standard password-reset
 * flow to activate their account.
 */
class RapporteurAndSessionLeadSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::SESSION_LEADS as [$name, $email]) {
            $this->upsertUser($name, $email, UserRole::SessionLead);
        }

        foreach (self::RAPPORTEURS as [$name, $email]) {
            $this->upsertUser($name, $email, UserRole::Rapporteur);
        }
    }

    private function upsertUser(string $name, string $email, UserRole $role): void
    {
        $email = strtolower(trim($email));
        $name = trim($name);

        $existing = User::where('email', $email)->first();

        if ($existing) {
            // Keep any elevated role a user already has, otherwise adopt this one.
            if ($existing->role === UserRole::Partner || $existing->role === null) {
                $existing->update(['role' => $role, 'name' => $name]);
            }

            return;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(str()->random(24)),
            'role' => $role,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private const SESSION_LEADS = [
        ['Ndekya Oriyo', 'Ndekya.Oriyo@amref.org'],
        ['Angelina Anthony', 'Angelina.Anthony@amref.org'],
        ['Asewe Jael Atieno', 'Jael.Atieno@amref.org'],
        ['Jacinta Kandie', 'Jacinta.Kandie@amref.org'],
        ['Loise Wanjiru', 'Loise.Miringu@amref.org'],
        ['Rose Betty Mukii', 'betty.mukii@amref.org'],
        ['Twambilire Mwabungulu', 'TMwabungulu@fp2030.org'],
        ['Alex Omari', 'Alex.Omari@amref.org'],
        ['Edna Mosiara', 'edna.mosiara@amref.org'],
        ['Emmanuel Musombi', 'emmanuel.musombi@amref.org'],
        ['Ida Rose Ndione', 'ida.ndione@amref.org'],
        ['Mable Jerop', 'mable.jerop@amref.org'],
        ['Nicole London', 'london@amrefusa.org'],
        ["Bryan Baleke Ng'ambi", 'Bryan.Ngambi@amref.org'],
        ['Ernest Mendy', 'Ernest.Mendy@amref.org'],
        ['Anne Kerubo', 'anne.kerubo@amref.org'],
        ['Ninabina Davie', 'Ninabina.Davie@amref.org'],
        ['Jackson Musembi', 'Jackson.Musembi@amref.org'],
        ['Catherine Ombasa', 'Catherine.Ombasa@amref.org'],
        ['Sheila Lumumba', 'sheila.lumumba@amref.org'],
        ['Shadrack Muema', 'Shadrack.Muema@amref.org'],
        ['Luundu Machona', 'Luundu.Machona@amref.org'],
    ];

    private const RAPPORTEURS = [
        ['Anne Goretti Karimi Munene', 'anne.munene@amref.org'],
        ['Barach Magdalene Apajok', 'barackjeidenna@gmail.com'],
        ['Benjamin Rutayisire', 'rutaben7@gmail.com'],
        ['Christine Muya', 'Christine.Muya@amref.ac.ke'],
        ['Dennis M. Kinyua', 'Dennis.Kinyua@Amref.org'],
        ['Diana Bulanda', 'diana.bulanda@amref.org'],
        ['Dianah Mokeira Nyagaka', 'nyagakadianah@gmail.com'],
        ['Dr. Lucy Muthoni Njiru', 'Lucy.Njiru@amref.ac.ke'],
        ['Dr. Okubatsion Okube Tekeste', 'Tekeste.Okube@amref.ac.ke'],
        ['Edel Koki', 'kokiedel99@gmail.com'],
        ['Geoffrey Mwenda Ikiara', 'Geoffrey.Ikiara@Amref.org'],
        ['Josephat Kimori', 'josephat.kimori@amref.org'],
        ['Levy Mkandawire', 'levy.mkandawire@amrefhealthafrica.onmicrosoft.com'],
        ['Teresia Onyango', 'Teresia.Onyango@amref.org'],
        ['Marieme Ly', 'marieme.ly@amref.org'],
        ['Marvin Mokaya', 'Marvin.Mokaya@Amref.org'],
        ['Maryanne Mugi', 'mugimaryanne@gmail.com'],
        ['Phillip Soita Wakoli', 'Philip.Soita@amref.ac.ke'],
        ['Norah Ogutu', 'Norah.Ogutu@Amrefhealthafrica.onmicrosoft.com'],
        ['Nyambura Gitonga', 'Nyambura.Gitonga@amref.org'],
        ['Precious Waweru', 'weruprecious@gmail.com'],
        ['Rabecca Kausa', 'Rabecca.Kausa@Amrefhealthafrica.onmicrosoft.com'],
        ['Richard Mbewe', 'richard.mbewe@amrefhealthafrica.onmicrosoft.com'],
        ['Ruth Warutere', 'ruth.warutere@amref.org'],
        ['Saida Kassim', 'saida.kassim@amref.org'],
        ['Samuel Muhula', 'Samuel.Muhula@Amref.org'],
        ['Sechelanji Nambela', 'sechelanji.nambela@amref.org'],
        ['Juliana Seipati Marcos Macamo', 'Julianauniteds@gmail.com'],
        ['Lilebo Faith Thipe', 'fhope.thipe@gmail.com'],
        ['Marie-Claire Wangari', 'mcwangari.wm@gmail.com'],
        ['Fatmata Sankoh', 'phatmatasankoh037@gmail.com'],
        ['Alexia Mshambala', 'Alexia.Mshambala@Amref.org'],
        ['Irene Alenga', 'Irene.Alenga@Amref.org'],
    ];
}
