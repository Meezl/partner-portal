import assert from 'node:assert/strict';
import test from 'node:test';

import {
    canAccessEoi,
    getEoiActionLabel,
    getEoiDescription,
    getQuickActionSpecs,
    getWorkflowPrompt,
} from '../../resources/js/lib/partner-workflow.js';

/** Every state a partner can be in, so none can quietly become a dead end. */
const ALL_STATUSES = [
    'draft',
    'interest_submitted',
    'rejected',
    'pending_agreement',
    'pending_payment',
    'confirmed',
    'onboarding',
    'submitted',
    'scheduled',
    'finalized',
];

test('editable eoi statuses can reopen the workflow', () => {
    assert.equal(canAccessEoi(null), true);
    assert.equal(canAccessEoi('draft'), true);
    assert.equal(canAccessEoi('interest_submitted'), true);
    assert.equal(canAccessEoi('rejected'), true);
    assert.equal(canAccessEoi('pending_agreement'), false);
});

test('eoi action labels match the partner state', () => {
    assert.equal(getEoiActionLabel(null), 'Start Expression of Interest');
    assert.equal(getEoiActionLabel('draft'), 'Continue Draft EOI');
    assert.equal(getEoiActionLabel('interest_submitted'), 'View / Edit EOI');
    assert.equal(
        getEoiActionLabel('rejected'),
        'Revise Expression of Interest',
    );
    assert.equal(getEoiActionLabel('pending_payment'), 'EOI Submitted');
});

test('eoi descriptions explain the next step', () => {
    assert.match(getEoiDescription(null), /not started/i);
    assert.match(getEoiDescription('draft'), /saved as a draft/i);
    assert.match(getEoiDescription('interest_submitted'), /under review/i);
    assert.match(getEoiDescription('rejected'), /needs changes/i);
});

test('dashboard quick actions expose the right workflow entry points', () => {
    assert.deepEqual(getQuickActionSpecs('pending_agreement', true), [
        {
            key: 'commitment',
            label: 'Confirm Package & Agreement',
            href: '/partner/commitment',
        },
        {
            key: 'invoices',
            label: 'View Invoices',
            href: '/partner/invoices',
        },
    ]);

    assert.deepEqual(getQuickActionSpecs('pending_payment', true), [
        {
            key: 'payment',
            label: 'Make Payment',
            href: '/partner/payment',
        },
        {
            key: 'invoices',
            label: 'View Invoices',
            href: '/partner/invoices',
        },
    ]);

    assert.deepEqual(getQuickActionSpecs('rejected', true), [
        {
            key: 'revise_eoi',
            label: 'Revise Expression of Interest',
            href: '/partner/expression-of-interest',
        },
        {
            key: 'invoices',
            label: 'View Invoices',
            href: '/partner/invoices',
        },
    ]);
});

test('a partner whose payment is confirmed is offered onboarding', () => {
    assert.deepEqual(getQuickActionSpecs('confirmed', true), [
        {
            key: 'start_onboarding',
            label: 'Start Onboarding',
            href: '/partner/onboarding',
        },
        {
            key: 'invoices',
            label: 'View Invoices',
            href: '/partner/invoices',
        },
    ]);

    const prompt = getWorkflowPrompt('confirmed', true);

    assert.equal(prompt.action.href, '/partner/onboarding');
    assert.equal(prompt.action.label, 'Start Onboarding');
    assert.equal(prompt.waiting, false);
    assert.match(prompt.title, /start onboarding/i);
});

test('no workflow stage leaves the partner without somewhere to go', () => {
    for (const status of ALL_STATUSES) {
        const actions = getQuickActionSpecs(status, true);
        const beyondInvoices = actions.filter(
            (action) => action.key !== 'invoices',
        );

        assert.ok(
            beyondInvoices.length > 0,
            `${status} offers nothing but the invoice list`,
        );

        const prompt = getWorkflowPrompt(status, true);

        assert.ok(prompt.title, `${status} has no prompt title`);
        assert.ok(prompt.description, `${status} has no prompt description`);
        assert.ok(prompt.action, `${status} has no prompt action`);
    }
});

test('a partner with nothing yet is prompted to start', () => {
    const prompt = getWorkflowPrompt(null, false);

    assert.equal(prompt.action.href, '/partner/expression-of-interest');
    assert.match(prompt.title, /start your expression of interest/i);
});

test('stages that wait on the AHAIC team say so', () => {
    assert.equal(getWorkflowPrompt('interest_submitted', true).waiting, true);
    assert.equal(getWorkflowPrompt('submitted', true).waiting, true);

    // Stages the partner can act on are not marked as waiting.
    assert.equal(getWorkflowPrompt('confirmed', true).waiting, false);
    assert.equal(getWorkflowPrompt('pending_payment', true).waiting, false);
    assert.equal(getWorkflowPrompt('onboarding', true).waiting, false);
});
