const AGREEMENT_STATUS_ORDER = ['pending', 'signed', 'verified'];

export function getAgreementStepState(currentStatus, stepKey) {
    const currentIdx = AGREEMENT_STATUS_ORDER.indexOf(currentStatus);
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
