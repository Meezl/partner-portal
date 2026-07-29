<?php

namespace App\Enums;

enum PartnerStatus: string
{
    case Draft = 'draft';
    case InterestSubmitted = 'interest_submitted';
    case Rejected = 'rejected';
    case PendingAgreement = 'pending_agreement';
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Onboarding = 'onboarding';
    case Submitted = 'submitted';
    case Scheduled = 'scheduled';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InterestSubmitted => 'Interest Submitted',
            self::Rejected => 'Rejected',
            self::PendingAgreement => 'Pending Agreement',
            self::PendingPayment => 'Pending Payment',
            self::Confirmed => 'Confirmed',
            self::Onboarding => 'Onboarding',
            self::Submitted => 'Submitted',
            self::Scheduled => 'Scheduled',
            self::Finalized => 'Finalized',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'text-gray-500',
            self::InterestSubmitted => 'text-blue-500',
            self::Rejected => 'text-red-600',
            self::PendingAgreement => 'text-yellow-600',
            self::PendingPayment => 'text-orange-500',
            self::Confirmed => 'text-green-600',
            self::Onboarding => 'text-indigo-500',
            self::Submitted => 'text-purple-500',
            self::Scheduled => 'text-teal-600',
            self::Finalized => 'text-green-700',
        };
    }
}
