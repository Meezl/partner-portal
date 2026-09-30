/**
 * The labels a partner ticked on a checklist, plus their "Other" note.
 *
 * Checklists are stored as one boolean per option key plus a free-text `other`
 * (see App\Support\OnboardingChecklists), and the labels live on the server, so
 * every screen that reviews a checklist reads it through here rather than
 * keeping its own copy of the wording.
 *
 * @param {Record<string, string>} options Option key → label.
 * @param {Record<string, boolean|string|null>|null|undefined} checklist
 * @returns {string[]}
 */
export function tickedChecklistLabels(options = {}, checklist = null) {
    if (!checklist) {
        return [];
    }

    const ticked = Object.entries(options)
        .filter(([key]) => checklist[key] === true)
        .map(([, label]) => label);

    const other =
        typeof checklist.other === 'string' ? checklist.other.trim() : '';

    return other === '' ? ticked : [...ticked, `Other: ${other}`];
}

/**
 * Social links a partner supplied, as [platform, url] pairs. Blank entries are
 * dropped so the review screens do not show empty rows.
 *
 * @param {Record<string, string|null>|null|undefined} socialMedia
 * @returns {Array<[string, string]>}
 */
export function filledSocialLinks(socialMedia = null) {
    return Object.entries(socialMedia ?? {}).filter(
        ([, url]) => typeof url === 'string' && url.trim() !== '',
    );
}
