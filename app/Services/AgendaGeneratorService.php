<?php

namespace App\Services;

use App\Models\Conference;
use App\Models\Partner;
use App\Models\SessionSchedule;

class AgendaGeneratorService
{
    public function generateMasterAgenda(Conference $conference): array
    {
        $schedules = SessionSchedule::with([
            'session.partner', 'room', 'timeSlot', 'resourceAssignments',
        ])
            ->whereHas('session', fn ($q) => $q->where('conference_id', $conference->id))
            ->get();

        $agenda = [];
        $grouped = $schedules->groupBy(fn ($s) => $s->timeSlot->date->format('Y-m-d'));

        foreach ($grouped->sortKeys() as $date => $daySchedules) {
            $slots = $daySchedules->groupBy('time_slot_id')->map(function ($slotSchedules) {
                $timeSlot = $slotSchedules->first()->timeSlot;

                return [
                    'time_slot' => $timeSlot,
                    'sessions' => $slotSchedules->map(fn ($s) => [
                        'session' => $s->session,
                        'room' => $s->room,
                        'resources' => $s->resourceAssignments,
                        'partner' => $s->session->partner,
                    ])->values()->all(),
                ];
            })->sortBy(fn ($s) => $s['time_slot']->start_time)->values()->all();

            $agenda[] = [
                'date' => $date,
                'slots' => $slots,
            ];
        }

        return $agenda;
    }

    public function generatePartnerAgenda(Partner $partner): array
    {
        $schedules = SessionSchedule::with(['session', 'room', 'timeSlot', 'resourceAssignments'])
            ->whereHas('session', fn ($q) => $q->where('partner_id', $partner->id))
            ->get();

        $agenda = [];
        $grouped = $schedules->groupBy(fn ($s) => $s->timeSlot->date->format('Y-m-d'));

        foreach ($grouped->sortKeys() as $date => $daySchedules) {
            $slots = $daySchedules->sortBy(fn ($s) => $s->timeSlot->start_time)->map(fn ($s) => [
                'time_slot' => $s->timeSlot,
                'session' => $s->session,
                'room' => $s->room,
                'resources' => $s->resourceAssignments,
            ])->values()->all();

            $agenda[] = [
                'date' => $date,
                'slots' => $slots,
            ];
        }

        return $agenda;
    }
}
