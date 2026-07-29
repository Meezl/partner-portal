export function getUnscheduledSessions(sessions = [], schedules = []) {
    return sessions.filter(
        (session) =>
            session.status === 'submitted' &&
            !schedules.some(
                (schedule) => schedule.conference_session_id === session.id,
            ),
    );
}

export function buildScheduleAssignmentPath(sessionId) {
    return `/admin/scheduling/sessions/${sessionId}/assign`;
}

export function buildScheduleBoardPath(sessionId = null) {
    return sessionId
        ? `/admin/scheduling?session=${sessionId}`
        : '/admin/scheduling';
}
