import assert from 'node:assert/strict';
import test from 'node:test';

import {
    canAccessEoi,
    getEoiActionLabel,
    getEoiDescription,
    getQuickActionSpecs,
} from '../../resources/js/lib/partner-workflow.js';

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
