import assert from 'node:assert/strict';
import test from 'node:test';

import {
    canAdvanceToReview,
    canFinalizeSubmission,
    getIncompleteOnboardingSections,
    getOnboardingOverallProgress,
    getOnboardingSectionHref,
    getOnboardingSections,
} from '../../resources/js/lib/onboarding-workflow.js';

test('onboarding section routes use the canonical onboarding paths', () => {
    assert.equal(getOnboardingSectionHref('organization'), '/partner/onboarding/organization');
    assert.equal(getOnboardingSectionHref('sessions'), '/partner/onboarding/sessions');
    assert.equal(getOnboardingSectionHref('communications'), '/partner/onboarding/communications');
    assert.equal(getOnboardingSectionHref('contacts'), '/partner/onboarding/contacts');
});

test('onboarding progress helpers calculate overall completion and missing sections', () => {
    const progress = {
        organization: 100,
        sessions: 75,
        communications: 100,
        contacts: 50,
    };

    assert.equal(getOnboardingOverallProgress(progress), 81);
    assert.deepEqual(getIncompleteOnboardingSections(progress), [
        'sessions',
        'contacts',
    ]);
    assert.equal(canAdvanceToReview(progress), false);

    const sections = getOnboardingSections(progress);
    assert.equal(sections[0].href, '/partner/onboarding/organization');
    assert.equal(sections[1].progress, 75);
});

test('final submission readiness requires fully complete onboarding and at least one session', () => {
    const fullProgress = {
        organization: 100,
        sessions: 100,
        communications: 100,
        contacts: 100,
    };

    assert.equal(canAdvanceToReview(fullProgress), true);
    assert.equal(canFinalizeSubmission(fullProgress, 0), false);
    assert.equal(canFinalizeSubmission(fullProgress, 1), true);
});
