<?php

namespace App\Enums;

enum ChangeRequestType: string
{
    case Time = 'time';
    case Room = 'room';
    case SessionDetails = 'session_details';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Time => 'Time',
            self::Room => 'Room',
            self::SessionDetails => 'Session Details',
            self::Other => 'Other',
        };
    }
}
