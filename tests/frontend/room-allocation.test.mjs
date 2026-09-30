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
        'Expected attendance (180) exceeds theatre style capacity (120).',
        'Room is not marked suitable for Panel sessions.',
    ]);
});

test('room allocation measures capacity against the requested seating', () => {
    const room = {
        capacity: 500,
        theatre_capacity: 500,
        round_capacity: 210,
        format_suitability: ['workshop'],
    };

    assert.deepEqual(
        buildRoomFitWarnings(
            { format: 'workshop', expected_participants: 300, special_requirements: { seating_type: 'round_table' } },
            room,
        ),
        ['Expected attendance (300) exceeds round table capacity (210).'],
    );

    assert.deepEqual(
        buildRoomFitWarnings(
            { format: 'workshop', expected_participants: 300, special_requirements: { seating_type: 'theatre' } },
            room,
        ),
        [],
    );
});

test('room allocation rejects a room the venue does not lay out that way', () => {
    assert.deepEqual(
        buildRoomFitWarnings(
            { format: 'workshop', expected_participants: 40, special_requirements: { seating_type: 'round_table' } },
            { capacity: 1200, theatre_capacity: 1200, round_capacity: null },
        ),
        ['Room is not set up as round table.'],
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
