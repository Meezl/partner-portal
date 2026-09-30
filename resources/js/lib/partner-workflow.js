export function canAccessEoi(status) {
    return (
        !status || ['draft', 'interest_submitted', 'rejected'].includes(status)
    );
}

export function getEoiActionLabel(status) {
    if (!status) {
        return 'Start Expression of Interest';
    }

    if (status === 'draft') {
        return 'Continue Draft EOI';
    }

    if (status === 'interest_submitted') {
        return 'View / Edit EOI';
    }

    if (status === 'rejected') {
        return 'Revise Expression of Interest';
    }

    return 'EOI Submitted';
}

export function getEoiDescription(status) {
    if (!status) {
        return 'You have not started an expression of interest yet. Begin one to apply for a partnership package.';
    }

    if (status === 'draft') {
        return 'Your expression of interest is saved as a draft. Review it and submit when you are ready.';
    }

    if (status === 'interest_submitted') {
        return 'Your expression of interest has been submitted and is currently under review by the AHAIC team.';
    }

    if (status === 'rejected') {
        return 'Your previous submission needs changes before it can move forward. Review the feedback from the AHAIC team and resubmit your expression of interest.';
    }

    return 'Your expression of interest has already moved into the next stage of the partnership process.';
}

export function getQuickActionSpecs(status, hasPartner) {
    const actions = [];

    if (!status) {
        actions.push({
            key: 'start_eoi',
            label: 'Start Expression of Interest',
            href: '/partner/expression-of-interest',
        });
    } else if (status === 'draft') {
        actions.push({
            key: 'draft_eoi',
            label: 'Continue Draft EOI',
            href: '/partner/expression-of-interest',
        });
    } else if (status === 'interest_submitted') {
        actions.push({
            key: 'edit_eoi',
            label: 'View / Edit Your EOI',
            href: '/partner/expression-of-interest',
        });
    } else if (status === 'rejected') {
        actions.push({
            key: 'revise_eoi',
            label: 'Revise Expression of Interest',
            href: '/partner/expression-of-interest',
        });
    }

    if (status === 'pending_agreement') {
        actions.push({
            key: 'commitment',
            label: 'Confirm Package & Agreement',
            href: '/partner/commitment',
        });
    }

    if (status === 'pending_payment') {
        actions.push({
            key: 'payment',
            label: 'Make Payment',
            href: '/partner/payment',
        });
    }

    // Payment is in, so onboarding is the next thing the partner does. Without
    // this the dashboard offered a confirmed partner nothing but their invoices.
    if (status === 'confirmed') {
        actions.push({
            key: 'start_onboarding',
            label: 'Start Onboarding',
            href: '/partner/onboarding',
        });
    }

    if (status === 'onboarding') {
        actions.push({
            key: 'onboarding',
            label: 'Continue Onboarding',
            href: '/partner/onboarding',
        });
    }

    if (status === 'submitted') {
        actions.push({
            key: 'review_submission',
            label: 'View Your Submission',
            href: '/partner/review',
        });
    }

    if (status === 'scheduled' || status === 'finalized') {
        actions.push({
            key: 'schedule',
            label: 'View Schedule',
            href: '/partner/schedule',
        });
    }

    if (hasPartner) {
        actions.push({
            key: 'invoices',
            label: 'View Invoices',
            href: '/partner/invoices',
        });
    }

    return actions;
}

/**
 * The one thing the partner should do next, as a prompt for the dashboard.
 *
 * Every stage is covered, including the ones where the partner is waiting on
 * the AHAIC team — saying "we are reviewing this" is more useful than an empty
 * panel, and it keeps a stage from silently becoming a dead end the way
 * `confirmed` once did.
 *
 * @param {string|null|undefined} status
 * @param {boolean} hasPartner
 * @returns {{key: string, title: string, description: string, waiting: boolean,
 *   action: {label: string, href: string}|null}}
 */
export function getWorkflowPrompt(status, hasPartner = true) {
    const prompt = (key, title, description, action = null, waiting = false) => ({
        key,
        title,
        description,
        action,
        waiting,
    });

    if (!hasPartner || !status) {
        return prompt(
            'start_eoi',
            'Start your expression of interest',
            'Tell us about your organization and pick the partnership package you are interested in.',
            { label: 'Start Expression of Interest', href: '/partner/expression-of-interest' },
        );
    }

    switch (status) {
        case 'draft':
            return prompt(
                'draft_eoi',
                'Finish your expression of interest',
                'Your expression of interest is saved as a draft. Complete it and submit when you are ready.',
                { label: 'Continue Draft EOI', href: '/partner/expression-of-interest' },
            );

        case 'interest_submitted':
            return prompt(
                'awaiting_review',
                'Your expression of interest is under review',
                'The AHAIC partnerships team is reviewing your submission. You can still edit it until they respond.',
                { label: 'View / Edit Your EOI', href: '/partner/expression-of-interest' },
                true,
            );

        case 'rejected':
            return prompt(
                'revise_eoi',
                'Your expression of interest needs changes',
                'Review the feedback from the AHAIC team, then update and resubmit your expression of interest.',
                { label: 'Revise Expression of Interest', href: '/partner/expression-of-interest' },
            );

        case 'pending_agreement':
            return prompt(
                'commitment',
                'Confirm your package and sign the agreement',
                'Review your partnership package, then sign the agreement to receive your invoice.',
                { label: 'Confirm Package & Agreement', href: '/partner/commitment' },
            );

        case 'pending_payment':
            return prompt(
                'payment',
                'Settle your invoice',
                'Pay now and upload your proof of payment, or send a purchase order to pay later.',
                { label: 'Make Payment', href: '/partner/payment' },
            );

        case 'confirmed':
            return prompt(
                'start_onboarding',
                'Payment confirmed — you can start onboarding',
                'Add your organization profile, sessions, communications needs and key contacts. You can complete the sections in any order.',
                { label: 'Start Onboarding', href: '/partner/onboarding' },
            );

        case 'onboarding':
            return prompt(
                'onboarding',
                'Continue your onboarding',
                'Finish the remaining onboarding sections, then submit everything for review.',
                { label: 'Continue Onboarding', href: '/partner/onboarding' },
            );

        case 'submitted':
            return prompt(
                'submitted',
                'Your submission is with the programme team',
                'Everything is in. The programme team is reviewing your sessions and will confirm your schedule.',
                { label: 'View Your Submission', href: '/partner/review' },
                true,
            );

        case 'scheduled':
            return prompt(
                'scheduled',
                'Your sessions have been scheduled',
                'Check the dates, times and rooms for your sessions, and request a change if something does not work.',
                { label: 'View Schedule', href: '/partner/schedule' },
            );

        case 'finalized':
            return prompt(
                'finalized',
                'Everything is confirmed',
                'Your partnership is finalized. Your schedule and conference materials are ready.',
                { label: 'View Schedule', href: '/partner/schedule' },
            );

        default:
            return prompt(
                'invoices',
                'Your partnership is in progress',
                'The AHAIC team will be in touch with your next step.',
                hasPartner ? { label: 'View Invoices', href: '/partner/invoices' } : null,
                true,
            );
    }
}
