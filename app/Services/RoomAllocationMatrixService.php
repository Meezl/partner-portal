<?php

namespace App\Services;

use App\Models\ConferenceSession;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RoomAllocationMatrixService
{
    public function build(Collection $rooms, Collection $timeSlots, Collection $schedules, Collection $sessions): array
    {
        $activeRooms = $rooms->where('is_active', true)->values();
        $scheduleIndex = $schedules->keyBy(fn ($schedule) => $this->scheduleKey($schedule->room_id, $schedule->time_slot_id));
        $unscheduledCount = $sessions
            ->filter(fn ($session) => ! $scheduleIndex->contains(fn ($schedule) => $schedule->conference_session_id === $session->id))
            ->count();

        $days = $timeSlots
            ->groupBy(fn ($slot) => $slot->date?->toDateString() ?? (string) $slot->date)
            ->map(function (Collection $slots, string $date) use ($activeRooms, $scheduleIndex) {
                $slotRows = $slots
                    ->sortBy(fn ($slot) => sprintf('%s-%s', $slot->start_time, $slot->end_time))
                    ->values()
                    ->map(function ($slot) use ($activeRooms, $scheduleIndex) {
                        $cells = $activeRooms->map(function ($room) use ($slot, $scheduleIndex) {
                            $schedule = $scheduleIndex->get($this->scheduleKey($room->id, $slot->id));
                            $warnings = $schedule && $schedule->session
                                ? $this->fitWarnings($schedule->session, $room)
                                : [];

                            return [
                                'room_id' => $room->id,
                                'room_name' => $room->name,
                                'room_capacity' => $room->capacity,
                                'schedule' => $schedule ? [
                                    'id' => $schedule->id,
                                    'conference_session_id' => $schedule->conference_session_id,
                                    'room_id' => $schedule->room_id,
                                    'time_slot_id' => $schedule->time_slot_id,
                                    'status' => $schedule->status,
                                    'notes' => $schedule->notes,
                                    'session' => $schedule->session ? [
                                        'id' => $schedule->session->id,
                                        'partner_id' => $schedule->session->partner_id,
                                        'title' => $schedule->session->title,
                                        'format' => $schedule->session->format?->value ?? (string) $schedule->session->format,
                                        'expected_participants' => $schedule->session->expected_participants,
                                    ] : null,
                                    'fit_warnings' => $warnings,
                                ] : null,
                            ];
                        })->values();

                        $scheduledCount = $cells->filter(fn (array $cell) => $cell['schedule'] !== null)->count();

                        return [
                            'id' => $slot->id,
                            'date' => $slot->date?->toDateString() ?? (string) $slot->date,
                            'start_time' => (string) $slot->start_time,
                            'end_time' => (string) $slot->end_time,
                            'label' => $slot->label,
                            'slot_type' => $slot->slot_type,
                            'scheduled_count' => $scheduledCount,
                            'available_rooms' => max($activeRooms->count() - $scheduledCount, 0),
                            'cells' => $cells->all(),
                        ];
                    });

                $scheduledSessions = $slotRows->sum('scheduled_count');
                $totalRoomSlots = max($activeRooms->count() * max($slotRows->count(), 1), 1);

                return [
                    'date' => $date,
                    'label' => $this->formatDateLabel($date),
                    'slot_count' => $slotRows->count(),
                    'scheduled_sessions' => $scheduledSessions,
                    'occupancy_rate' => (int) round(($scheduledSessions / $totalRoomSlots) * 100),
                    'slots' => $slotRows->all(),
                ];
            })
            ->sortKeys()
            ->values();

        $roomStats = $activeRooms->map(function (Room $room) use ($schedules, $timeSlots) {
            $assignmentCount = $schedules->where('room_id', $room->id)->count();
            $totalSlots = max($timeSlots->count(), 1);

            return [
                'room_id' => $room->id,
                'name' => $room->name,
                'capacity' => $room->capacity,
                'assignment_count' => $assignmentCount,
                'utilization_rate' => (int) round(($assignmentCount / $totalSlots) * 100),
                'format_suitability' => $room->format_suitability ?? [],
                'is_active' => $room->is_active,
            ];
        })->values();

        $totalRoomSlots = max($activeRooms->count() * max($timeSlots->count(), 1), 1);

        return [
            'summary' => [
                'active_rooms' => $activeRooms->count(),
                'time_slots' => $timeSlots->count(),
                'scheduled_sessions' => $schedules->count(),
                'unscheduled_sessions' => $unscheduledCount,
                'occupancy_rate' => (int) round(($schedules->count() / $totalRoomSlots) * 100),
            ],
            'days' => $days->all(),
            'room_stats' => $roomStats->all(),
        ];
    }

    public function fitWarnings(ConferenceSession $session, Room $room): array
    {
        $warnings = [];

        if ($session->expected_participants && $room->capacity && $session->expected_participants > $room->capacity) {
            $warnings[] = sprintf(
                'Expected attendance (%d) exceeds room capacity (%d).',
                $session->expected_participants,
                $room->capacity,
            );
        }

        $supportedFormats = collect($room->format_suitability ?? [])
            ->map(fn ($format) => Str::lower((string) $format))
            ->filter()
            ->values();
        $sessionFormat = Str::lower($session->format?->value ?? (string) $session->format);

        if ($sessionFormat !== '' && $supportedFormats->isNotEmpty() && ! $supportedFormats->contains($sessionFormat)) {
            $warnings[] = sprintf(
                'Room is not marked suitable for %s sessions.',
                Str::headline(str_replace('_', ' ', $sessionFormat)),
            );
        }

        return $warnings;
    }

    private function scheduleKey(int $roomId, int $timeSlotId): string
    {
        return $roomId.'-'.$timeSlotId;
    }

    private function formatDateLabel(string $date): string
    {
        return Carbon::parse($date)->format('D, M j');
    }
}
