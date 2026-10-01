import assert from 'node:assert/strict';
import test from 'node:test';

import {
    buildRoomFitWarnings,
    summarizeAllocationDays,
} from '../../resources/js/lib/room-allocation.js';

test('room allocation flags a format the room is not marked for', () => {
    const warnings = buildRoomFitWarnings(
        { format: 'panel' },
        { capacity: 120, format_suitability: ['workshop'] },
    );

    assert.deepEqual(warnings, ['Room is not marked suitable for Panel sessions.']);
});

test('room allocation no longer judges a room on seats', () => {
    // The room is far too small for the session, and a room with no declared
    // formats takes anything: neither is a warning any more.
    assert.deepEqual(
        buildRoomFitWarnings(
            { format: 'workshop', expected_participants: 'over 150' },
            { capacity: 10, format_suitability: [] },
        ),
        [],
    );
});

test('room allocation summarizes workbook-style day totals', () => {
    const summary = summarizeAllocationDays([
        { slot_count: 4, scheduled_sessions: 7 },
        { slot_count: 3, scheduled_sessions: 5 },
    ]);

    assert.deepEqual(summary, {
        slotCount: 7,
        scheduledSessions: 12,
    });
});
