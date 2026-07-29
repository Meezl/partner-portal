import assert from 'node:assert/strict';
import test from 'node:test';

import { getAgreementStepState } from '../../resources/js/lib/agreement-workflow.js';

test('agreement workflow marks the active step as current', () => {
    assert.equal(getAgreementStepState('pending', 'pending'), 'current');
    assert.equal(getAgreementStepState('signed', 'signed'), 'current');
});

test('agreement workflow marks previous steps as completed', () => {
    assert.equal(getAgreementStepState('signed', 'pending'), 'completed');
    assert.equal(getAgreementStepState('verified', 'signed'), 'completed');
});

test('agreement workflow marks future steps as upcoming', () => {
    assert.equal(getAgreementStepState('pending', 'verified'), 'upcoming');
    assert.equal(getAgreementStepState('signed', 'verified'), 'upcoming');
});

test('agreement workflow handles unknown statuses defensively', () => {
    assert.equal(getAgreementStepState('unknown', 'pending'), 'upcoming');
    assert.equal(getAgreementStepState('pending', 'unknown'), 'upcoming');
});
