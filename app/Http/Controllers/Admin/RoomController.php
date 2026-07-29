<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SessionFormat;
use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    /**
     * List all rooms.
     */
    public function index(): Response
    {
        $conference = Conference::where('status', 'active')->latest()->first();
        $rooms = Room::withCount('sessionSchedules')
            ->when($conference, fn ($query) => $query->where('conference_id', $conference->id))
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Rooms/Index', [
            'rooms' => $rooms,
            'formatOptions' => collect(SessionFormat::cases())->map(fn (SessionFormat $format) => [
                'value' => $format->value,
                'label' => $format->label(),
            ])->values(),
        ]);
    }

    /**
     * Store a new room.
     */
    public function store(Request $request): RedirectResponse
    {
        $formats = collect(SessionFormat::cases())->map->value->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'building' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:50'],
            'format_suitability' => ['nullable', 'array'],
            'format_suitability.*' => ['string', Rule::in($formats)],
            'equipment' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $conference = Conference::where('status', 'active')->latest()->first();

        if (! $conference) {
            return back()->with('error', 'No active conference found.');
        }

        Room::create([
            'conference_id' => $conference->id,
            'name' => $validated['name'],
            'capacity' => $validated['capacity'],
            'building' => $validated['building'] ?? null,
            'floor' => $validated['floor'] ?? null,
            'format_suitability' => $validated['format_suitability'] ?? [],
            'equipment' => $this->parseEquipment($validated['equipment'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('success', 'Room created successfully.');
    }

    /**
     * Update an existing room.
     */
    public function update(Request $request, Room $room): RedirectResponse
    {
        $formats = collect(SessionFormat::cases())->map->value->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'building' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:50'],
            'format_suitability' => ['nullable', 'array'],
            'format_suitability.*' => ['string', Rule::in($formats)],
            'equipment' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $room->update([
            'name' => $validated['name'],
            'capacity' => $validated['capacity'],
            'building' => $validated['building'] ?? null,
            'floor' => $validated['floor'] ?? null,
            'format_suitability' => $validated['format_suitability'] ?? [],
            'equipment' => $this->parseEquipment($validated['equipment'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', 'Room updated successfully.');
    }

    /**
     * Delete a room if no schedules are assigned.
     */
    public function destroy(Room $room): RedirectResponse
    {
        if ($room->sessionSchedules()->exists()) {
            return back()->with('error', 'Cannot delete a room with assigned sessions. Please reassign sessions first.');
        }

        $room->delete();

        return back()->with('success', 'Room deleted successfully.');
    }

    private function parseEquipment(?string $equipment): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $equipment ?? ''))
            ->filter(fn ($line) => filled(trim($line)))
            ->mapWithKeys(function ($line) {
                [$key, $value] = array_pad(explode(':', $line, 2), 2, '');

                return [trim($key) => trim($value) ?: 'yes'];
            })
            ->all();
    }
}
