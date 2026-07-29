<?php

namespace Database\Factories;

use App\Models\ConferenceSession;
use App\Models\Room;
use App\Models\SessionSchedule;
use App\Models\TimeSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionSchedule>
 */
class SessionScheduleFactory extends Factory
{
    protected $model = SessionSchedule::class;

    public function definition(): array
    {
        return [
            'conference_session_id' => ConferenceSession::factory(),
            'room_id' => Room::factory(),
            'time_slot_id' => TimeSlot::factory(),
            'assigned_by' => null,
            'status' => 'scheduled',
            'notes' => null,
        ];
    }
}
