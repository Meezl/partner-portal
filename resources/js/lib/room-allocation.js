function formatTitle(value = '') {
    return String(value)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

/**
 * Reasons a session does not suit a room. Mirrors the server-side rules in
 * RoomAllocationMatrixService::fitWarnings().
 *
 * Rooms are no longer judged on seats: sessions declare a headcount band
 * rather than a number, and the venue sets rooms up to suit, so only the
 * room's format suitability is checked.
 *
 * @param {{format?: string|null}|null|undefined} session
 * @param {{format_suitability?: string[]|null, name?: string}|null|undefined} room
 * @returns {string[]}
 */
export function buildRoomFitWarnings(session = null, room = null) {
    if (!session || !room) {
        return [];
    }

    const warnings = [];
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
