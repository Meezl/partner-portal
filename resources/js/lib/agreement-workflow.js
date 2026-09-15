const AGREEMENT_STATUS_ORDER = ['pending', 'signed', 'verified'];

export function getAgreementStepState(currentStatus, stepKey) {
    // A rejected agreement is back to awaiting a signature.
    const status = currentStatus === 'rejected' ? 'pending' : currentStatus;
    const currentIdx = AGREEMENT_STATUS_ORDER.indexOf(status);
    const stepIdx = AGREEMENT_STATUS_ORDER.indexOf(stepKey);

    if (currentIdx === -1 || stepIdx === -1) {
        return 'upcoming';
    }

    if (stepIdx < currentIdx) {
        return 'completed';
    }

    if (stepIdx === currentIdx) {
        return 'current';
    }

    return 'upcoming';
}
