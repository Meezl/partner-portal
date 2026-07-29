import assert from 'node:assert/strict';
import test from 'node:test';

import {
    buildRoomFitWarnings,
    summarizeAllocationDays,
} from '../../resources/js/lib/room-allocation.js';

test('room allocation flags capacity and format mismatches', () => {
    const warnings = buildRoomFitWarnings(
        {
            format: 'panel',
            expected_participants: 180,
        },
        {
            capacity: 120,
            format_suitability: ['workshop'],
        },
    );

    assert.deepEqual(warnings, [
        'Expected attendance (180) exceeds room capacity (120).',
        'Room is not marked suitable for Panel sessions.',
    ]);
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
