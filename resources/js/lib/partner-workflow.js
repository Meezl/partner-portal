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

    if (status === 'onboarding') {
        actions.push({
            key: 'onboarding',
            label: 'Continue Onboarding',
            href: '/partner/onboarding',
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
