import assert from 'node:assert/strict';
import test from 'node:test';

import {
    filledSocialLinks,
    tickedChecklistLabels,
} from '../../resources/js/lib/checklists.js';

const OPTIONS = {
    additional_furniture: 'Additional furniture',
    storage_space: 'Storage space',
    av_equipment: 'AV equipment/screens',
};

test('checklist keeps the server wording and the option order', () => {
    assert.deepEqual(
        tickedChecklistLabels(OPTIONS, {
            av_equipment: true,
            additional_furniture: true,
            storage_space: false,
            other: null,
        }),
        ['Additional furniture', 'AV equipment/screens'],
    );
});

test('checklist appends the other note and ignores a blank one', () => {
    assert.deepEqual(
        tickedChecklistLabels(OPTIONS, { storage_space: true, other: '  A fridge  ' }),
        ['Storage space', 'Other: A fridge'],
    );

    assert.deepEqual(
        tickedChecklistLabels(OPTIONS, { storage_space: true, other: '   ' }),
        ['Storage space'],
    );
});

test('checklist reads an unanswered checklist as nothing ticked', () => {
    assert.deepEqual(tickedChecklistLabels(OPTIONS, null), []);
    assert.deepEqual(tickedChecklistLabels(OPTIONS, {}), []);
});

test('social links drop blanks', () => {
    assert.deepEqual(
        filledSocialLinks({ website: 'https://example.org', twitter: '', linkedin: null }),
        [['website', 'https://example.org']],
    );

    assert.deepEqual(filledSocialLinks(null), []);
});
