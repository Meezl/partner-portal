<?php

namespace App\Services;

use App\Models\Conference;
use App\Models\SessionSchedule;

class ConflictDetectionService
{
    public function detectConflicts(Conference $conference): array
    {
        $schedules = SessionSchedule::with(['session.partner', 'room', 'timeSlot'])
            ->whereHas('session', fn ($q) => $q->where('conference_id', $conference->id))
            ->get();

        $conflicts = [];

        // Room conflicts: same room, same time slot
        $roomGroups = $schedules->groupBy(fn ($s) => $s->room_id.'-'.$s->time_slot_id);
        foreach ($roomGroups as $key => $group) {
            if ($group->count() > 1) {
                $conflicts[] = [
                    'id' => $group->first()->id,
                    'type' => 'room_double_booking',
                    'description' => 'Multiple sessions assigned to the same room at the same time',
                    'room' => [
                        'id' => $group->first()->room?->id ?? 0,
                        'name' => $group->first()->room?->name ?? 'Unknown',
                    ],
                    'time_slot' => [
                        'date' => (string) $group->first()->timeSlot?->date,
                        'start_time' => (string) $group->first()->timeSlot?->start_time,
                        'end_time' => (string) $group->first()->timeSlot?->end_time,
                    ],
                    'sessions' => $group->map(fn ($s) => [
                        'id' => $s->session->id,
                        'title' => $s->session->title,
                        'partner_name' => $s->session->partner->organization_name ?? 'Unknown',
                        'format' => $s->session->format?->value ?? (string) $s->session->format,
                    ])->values()->all(),
                    'suggested_resolution' => 'Move one of the sessions to another room or time slot to eliminate the double booking.',
                ];
            }
        }

        // Partner conflicts: same partner, overlapping time slots
        $partnerGroups = $schedules->groupBy(fn ($s) => $s->session->partner_id ?? 0);
        foreach ($partnerGroups as $partnerId => $partnerSchedules) {
            $timeSlotGroups = $partnerSchedules->groupBy('time_slot_id');
            foreach ($timeSlotGroups as $tsId => $group) {
                if ($group->count() > 1) {
                    $conflicts[] = [
                        'id' => $group->first()->id,
                        'type' => 'partner_overlap',
                        'description' => 'Partner has multiple sessions at the same time',
                        'time_slot' => [
                            'date' => (string) $group->first()->timeSlot?->date,
                            'start_time' => (string) $group->first()->timeSlot?->start_time,
                            'end_time' => (string) $group->first()->timeSlot?->end_time,
                        ],
                        'sessions' => $group->map(fn ($s) => [
                            'id' => $s->session->id,
                            'title' => $s->session->title,
                            'partner_name' => $s->session->partner->organization_name ?? 'Unknown',
                            'format' => $s->session->format?->value ?? (string) $s->session->format,
                        ])->values()->all(),
                        'suggested_resolution' => 'Move one of the partner sessions to a different time slot so the organization is not double booked.',
                    ];
                }
            }
        }

        return $conflicts;
    }

    public function suggestAlternatives(int $sessionId, Conference $conference): array
    {
        $availableSlots = [];
        $rooms = $conference->rooms()->where('is_active', true)->get();
        $timeSlots = $conference->timeSlots()->where('slot_type', 'session')->get();

        foreach ($rooms as $room) {
            foreach ($timeSlots as $slot) {
                $isOccupied = SessionSchedule::where('room_id', $room->id)
                    ->where('time_slot_id', $slot->id)
                    ->exists();

                if (! $isOccupied) {
                    $availableSlots[] = [
                        'room' => $room,
                        'time_slot' => $slot,
                    ];
                }
            }
        }

        return array_slice($availableSlots, 0, 5);
    }
}
