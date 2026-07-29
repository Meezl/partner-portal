<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Finance = 'finance';
    case Programme = 'programme';
    case Pco = 'pco';
    case Communications = 'communications';
    case Partnerships = 'partnerships';
    case Partner = 'partner';
    case SessionLead = 'session_lead';
    case Rapporteur = 'rapporteur';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Finance => 'Finance',
            self::Programme => 'Programme',
            self::Pco => 'PCO',
            self::Communications => 'Communications',
            self::Partnerships => 'Partnerships',
            self::Partner => 'Partner',
            self::SessionLead => 'Session Lead',
            self::Rapporteur => 'Rapporteur',
        };
    }
}
