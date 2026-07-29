<?php

namespace Database\Seeders;

use App\Models\Booth;
use App\Models\Conference;
use App\Models\Room;
use App\Models\SessionSlot;
use Illuminate\Database\Seeder;

class SessionSlotSeeder extends Seeder
{
    /**
     * Build a session-slot inventory and booth inventory based on the
     * AHAIC "Session Slots & Booths Scheduling Matrix" workbook.
     *
     * Slot shape per day (mirrors the matrix; AMREF-led plenaries are
     * created but marked non-assignable):
     *
     *  Day 0 (arrival/eve):  Youth Summit, 1 Reception
     *  Day 1: 4 Breakfast | Plenary 1 | 6 Parallel | 6 Parallel | Plenary 2 | POP 1 | Reception
     *  Day 2: 4 Breakfast | Plenary 3 | 6 Parallel | 6 Parallel | Plenary 4 | POP 2 | Reception
     *  Day 3: 3 Breakfast | 5 Parallel | 5 Parallel | POP 3 | Plenary 5
     */
    public function run(): void
    {
        $conference = Conference::query()->orderBy('id')->first();

        if (! $conference) {
            return;
        }

        $rooms = Room::where('conference_id', $conference->id)->pluck('id', 'name');
        $plenaryRoomId = $rooms->get('Auditorium');
        $r = fn (string $name) => $rooms->get($name);

        // Parallel-session room rotation per track, taken from the
        // matrix's Master Sessions Sheet / Day 1–3 tabs.
        $parallelRooms = [
            1 => ['AD10', 'AD12', 'AD11', 'MH1', 'MH4', 'MH2'],  // Track 1
            2 => ['AD12', 'MH1', 'MH4', 'AD11', 'AD10', 'MH2'],  // Track 2
            3 => ['MH3',  'AD11', 'MH4',  'MH1', 'MH2', 'AD10'], // Track 3
            4 => ['AD12', 'AD10', 'MH4',  'MH1', 'MH3', 'AD11'], // Track 4
            5 => ['MH1',  'AD11', 'AD12', 'MH3', 'MH2'],          // Track 5
            6 => ['MH1',  'MH4',  'MH2',  'AD11', 'AD12'],        // Track 6
        ];
        $breakfastRooms = ['MH1', 'MH2', 'MH3', 'MH4'];
        $popRoom        = 'Foyer 1C';
        $receptionRooms = ['MH4', 'MH2', 'MH1', 'AD12']; // RT 0/1/2 spread

        $startDate = $conference->start_date;
        $order = 0;
        $slots = [];

        // ---- Day 0 ----
        $slots[] = $this->slot($conference->id, 0, $startDate, 'Youth Summit', 'other',     'YS',  '13:00-18:00', 'theatre',  $r('MH4'), false, ++$order);
        $slots[] = $this->slot($conference->id, 0, $startDate, 'Reception 0', 'reception', 'RT 0', '16:00-17:30', 'cocktail', $r('MH1'), true,  ++$order);

        // ---- Day 1 ----
        for ($i = 1; $i <= 4; $i++) {
            $slots[] = $this->slot($conference->id, 1, $startDate, "Breakfast $i", 'breakfast', 'BT 1', '08:00-09:30', 'round', $r($breakfastRooms[$i - 1]), true, ++$order);
        }
        $slots[] = $this->slot($conference->id, 1, $startDate, 'Plenary 1', 'plenary', null, '09:30-11:00', 'theatre', $plenaryRoomId, false, ++$order);
        for ($i = 1; $i <= 6; $i++) {
            $slots[] = $this->slot($conference->id, 1, $startDate, "Parallel 1.$i", 'parallel', 'Parallel Track 1', '11:30-13:00', 'round', $r($parallelRooms[1][$i - 1]), true, ++$order);
        }
        for ($i = 1; $i <= 6; $i++) {
            $slots[] = $this->slot($conference->id, 1, $startDate, "Parallel 2.$i", 'parallel', 'Parallel Track 2', '14:00-15:30', 'round', $r($parallelRooms[2][$i - 1]), true, ++$order);
        }
        $slots[] = $this->slot($conference->id, 1, $startDate, 'Plenary 2',   'plenary',  null,   '16:00-17:30', 'theatre',     $plenaryRoomId,    false, ++$order);
        $slots[] = $this->slot($conference->id, 1, $startDate, 'POP 1',       'pop',      'RT 1', '18:00-19:30', 'small-stage', $r($popRoom),      true,  ++$order);
        $slots[] = $this->slot($conference->id, 1, $startDate, 'Reception 1', 'reception','RT 1', '18:00-20:00', 'cocktail',    $r($receptionRooms[1]), true, ++$order);

        // ---- Day 2 ----
        for ($i = 5; $i <= 8; $i++) {
            $slots[] = $this->slot($conference->id, 2, $startDate, "Breakfast $i", 'breakfast', 'BT 2', '08:00-09:30', 'round', $r($breakfastRooms[$i - 5]), true, ++$order);
        }
        $slots[] = $this->slot($conference->id, 2, $startDate, 'Plenary 3', 'plenary', null, '09:30-11:00', 'theatre', $plenaryRoomId, false, ++$order);
        for ($i = 1; $i <= 6; $i++) {
            $slots[] = $this->slot($conference->id, 2, $startDate, "Parallel 3.$i", 'parallel', 'Parallel Track 3', '11:30-13:00', 'round', $r($parallelRooms[3][$i - 1]), true, ++$order);
        }
        for ($i = 1; $i <= 6; $i++) {
            $slots[] = $this->slot($conference->id, 2, $startDate, "Parallel 4.$i", 'parallel', 'Parallel Track 4', '14:00-15:30', 'round', $r($parallelRooms[4][$i - 1]), true, ++$order);
        }
        $slots[] = $this->slot($conference->id, 2, $startDate, 'Plenary 4',   'plenary',  null,   '16:00-17:30', 'theatre',     $plenaryRoomId,    false, ++$order);
        $slots[] = $this->slot($conference->id, 2, $startDate, 'POP 2',       'pop',      'RT 2', '18:00-19:30', 'small-stage', $r($popRoom),      true,  ++$order);
        $slots[] = $this->slot($conference->id, 2, $startDate, 'Reception 2', 'reception','RT 2', '18:00-20:00', 'cocktail',    $r($receptionRooms[2]), true, ++$order);

        // ---- Day 3 ----
        for ($i = 9; $i <= 11; $i++) {
            $slots[] = $this->slot($conference->id, 3, $startDate, "Breakfast $i", 'breakfast', 'BT 3', '08:00-09:30', 'round', $r($breakfastRooms[$i - 9]), true, ++$order);
        }
        for ($i = 1; $i <= 5; $i++) {
            $slots[] = $this->slot($conference->id, 3, $startDate, "Parallel 5.$i", 'parallel', 'Parallel Track 5', '09:30-11:00', 'round', $r($parallelRooms[5][$i - 1]), true, ++$order);
        }
        for ($i = 1; $i <= 5; $i++) {
            $slots[] = $this->slot($conference->id, 3, $startDate, "Parallel 6.$i", 'parallel', 'Parallel Track 6', '11:30-13:00', 'round', $r($parallelRooms[6][$i - 1]), true, ++$order);
        }
        $slots[] = $this->slot($conference->id, 3, $startDate, 'POP 3',     'pop',     null, '13:00-14:00', 'small-stage', $r($popRoom),   true,  ++$order);
        $slots[] = $this->slot($conference->id, 3, $startDate, 'Plenary 5', 'plenary', null, '14:00-16:00', 'theatre',     $plenaryRoomId, false, ++$order);

        foreach ($slots as $slot) {
            SessionSlot::updateOrCreate(
                ['conference_id' => $slot['conference_id'], 'slot_code' => $slot['slot_code']],
                $slot,
            );
        }

        // ---- Booths (matrix tab "Allocation matrix tables" §3) ----
        $booths = [];
        for ($n = 1; $n <= 25; $n++) {
            $booths[] = ['zone' => 'Foyer 1A', 'booth_number' => (string) $n];
        }
        for ($n = 26; $n <= 40; $n++) {
            $booths[] = ['zone' => 'Foyer 1B', 'booth_number' => (string) $n];
        }
        foreach ($booths as $b) {
            Booth::updateOrCreate(
                ['conference_id' => $conference->id, 'zone' => $b['zone'], 'booth_number' => $b['booth_number']],
                ['size' => '3x3', 'status' => 'available'],
            );
        }
    }

    private function slot(int $conferenceId, int $dayIndex, $startDate, string $code, string $category, ?string $track, string $timeLabel, string $format, ?int $roomId, bool $assignable, int $order): array
    {
        [$startTime, $endTime] = array_map('trim', explode('-', $timeLabel));
        $date = $startDate ? $startDate->copy()->addDays($dayIndex)->toDateString() : null;

        return [
            'conference_id' => $conferenceId,
            'slot_code' => $code,
            'slot_category' => $category,
            'track_label' => $track,
            'day_index' => $dayIndex,
            'date' => $date,
            'time_label' => $timeLabel,
            'start_time' => $startTime.':00',
            'end_time' => $endTime.':00',
            'default_room_id' => $roomId,
            'default_format' => $format,
            'is_assignable' => $assignable,
            'sort_order' => $order,
        ];
    }
}
