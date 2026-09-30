function formatTitle(value = '') {
    return String(value)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

/**
 * The two seating arrangements the venue offers, and the room field holding
 * capacity for each. Mirrors App\Enums\SeatingArrangement.
 */
const SEATING = {
    theatre: { label: 'theatre style', field: 'theatre_capacity' },
    round_table: { label: 'round table', field: 'round_capacity' },
};

/**
 * Normalise a stored seating value to one of the two arrangements. Mirrors
 * SeatingArrangement::fromLegacy() — anything else, including the free-text
 * values stored before the arrangements were narrowed, reads as theatre.
 *
 * @param {string|null|undefined} value
 * @returns {'theatre'|'round_table'}
 */
export function normalizeSeating(value) {
    const seating = String(value ?? '').trim().toLowerCase();

    return ['round_table', 'round_tables', 'round', 'boardroom', 'u_shape'].includes(seating)
        ? 'round_table'
        : 'theatre';
}

/**
 * How many people a room seats in the given arrangement, or null when the
 * venue does not lay the room out that way. Mirrors Room::capacityFor().
 *
 * @param {{capacity?: number|null, theatre_capacity?: number|null, round_capacity?: number|null}|null|undefined} room
 * @param {'theatre'|'round_table'} seating
 * @returns {number|null}
 */
export function roomCapacityFor(room, seating) {
    if (!room) {
        return null;
    }

    let capacity = room[SEATING[seating].field];

    if ((capacity === null || capacity === undefined) && seating === 'theatre') {
        capacity = room.capacity;
    }

    return Number(capacity) > 0 ? Number(capacity) : null;
}

/**
 * Reasons a session does not fit a room. Mirrors the server-side rules in
 * RoomAllocationMatrixService::fitWarnings().
 *
 * @param {{expected_participants?: number|null, format?: string|null, special_requirements?: Record<string, unknown>|null}|null|undefined} session
 * @param {{capacity?: number|null, theatre_capacity?: number|null, round_capacity?: number|null, format_suitability?: string[]|null, name?: string}|null|undefined} room
 * @returns {string[]}
 */
export function buildRoomFitWarnings(session = null, room = null) {
    if (!session || !room) {
        return [];
    }

    const warnings = [];
    const expectedParticipants = Number(session.expected_participants || 0);
    const seating = normalizeSeating(session.special_requirements?.seating_type);
    const capacity = roomCapacityFor(room, seating);

    if (capacity === null) {
        warnings.push(`Room is not set up as ${SEATING[seating].label}.`);
    } else if (expectedParticipants > capacity) {
        warnings.push(
            `Expected attendance (${expectedParticipants}) exceeds ${SEATING[seating].label} capacity (${capacity}).`,
        );
    }

    const supportedFormats = Array.isArray(room.format_suitability)
        ? room.format_suitability
              .map((format) => String(format).toLowerCase())
              .filter(Boolean)
        : [];
    const sessionFormat = String(session.format || '').toLowerCase();

    if (
        sessionFormat &&
        supportedFormats.length > 0 &&
        !supportedFormats.includes(sessionFormat)
    ) {
        warnings.push(
            `Room is not marked suitable for ${formatTitle(sessionFormat)} sessions.`,
        );
    }

    return warnings;
}

export function summarizeAllocationDays(days = []) {
    return days.reduce(
        (summary, day) => {
            summary.slotCount += Number(day.slot_count || 0);
            summary.scheduledSessions += Number(day.scheduled_sessions || 0);

            return summary;
        },
        { slotCount: 0, scheduledSessions: 0 },
    );
}
