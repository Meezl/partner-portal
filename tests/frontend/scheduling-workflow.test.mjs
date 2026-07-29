import assert from 'node:assert/strict';
import test from 'node:test';

import {
    buildScheduleAssignmentPath,
    buildScheduleBoardPath,
    getUnscheduledSessions,
} from '../../resources/js/lib/scheduling-workflow.js';

test('scheduling workflow identifies submitted sessions that still need assignments', () => {
    const sessions = [
        { id: 1, status: 'submitted' },
        { id: 2, status: 'scheduled' },
        { id: 3, status: 'submitted' },
    ];
    const schedules = [{ conference_session_id: 3 }];

    assert.deepEqual(getUnscheduledSessions(sessions, schedules), [
        { id: 1, status: 'submitted' },
    ]);
});

test('scheduling workflow builds canonical admin paths', () => {
    assert.equal(buildScheduleAssignmentPath('42'), '/admin/scheduling/sessions/42/assign');
    assert.equal(buildScheduleBoardPath(), '/admin/scheduling');
    assert.equal(buildScheduleBoardPath(99), '/admin/scheduling?session=99');
});
