const SECTION_DEFINITIONS = [
    {
        key: 'organization',
        title: 'Organization Profile',
        description: 'Logo, description, social media, and exhibition preferences',
    },
    {
        key: 'sessions',
        title: 'Session Submissions',
        description: 'Conference sessions, formats, and details',
    },
    {
        key: 'communications',
        title: 'Communications & Branding',
        description: 'Branding requirements, media contacts, and assets',
    },
    {
        key: 'contacts',
        title: 'Contacts',
        description: 'Session leads, communications leads, and additional contacts',
    },
];

export function getOnboardingSectionHref(sectionKey) {
    return `/partner/onboarding/${sectionKey}`;
}

export function getOnboardingSections(progress = {}) {
    return SECTION_DEFINITIONS.map((section) => ({
        ...section,
        href: getOnboardingSectionHref(section.key),
        progress: Number(progress?.[section.key] ?? 0),
    }));
}

export function getOnboardingOverallProgress(progress = {}) {
    const sections = getOnboardingSections(progress);

    return Math.round(
        sections.reduce((sum, section) => sum + section.progress, 0) /
            sections.length,
    );
}

export function getIncompleteOnboardingSections(progress = {}) {
    return getOnboardingSections(progress)
        .filter((section) => section.progress < 100)
        .map((section) => section.key);
}

export function canAdvanceToReview(progress = {}) {
    return getIncompleteOnboardingSections(progress).length === 0;
}

export function canFinalizeSubmission(progress = {}, sessionsCount = 0) {
    return canAdvanceToReview(progress) && sessionsCount > 0;
}
