/**
 * Session format labels, mirroring App\Enums\SessionFormat::label().
 *
 * Views that only receive the stored slug (the scheduling matrix, the admin
 * session list, room suitability badges) use this instead of title-casing the
 * slug, which loses the "Keynote / Featured Address" style of the real labels.
 */
export const SESSION_FORMAT_LABELS: Record<string, string> = {
    roundtable: 'Roundtable',
    panel: 'Panel Discussion',
    fireside_chat: 'Fireside Chat',
    keynote: 'Keynote / Featured Address',
    workshop: 'Workshop / Masterclass',
    interactive_dialogue: 'Interactive Dialogue',
    live_studio: 'Live Studio Session',
    stand_up: 'Stand-up Session',
    breakout: 'Breakout Session',
    networking: 'Networking / Reception',
    cocktail: 'Cocktail',
    showcase: 'Product / Solution Showcase',
    other: 'Other',
};

export function sessionFormatLabel(
    format: string | null | undefined,
    fallback = 'Session',
): string {
    if (!format) {
        return fallback;
    }

    return (
        SESSION_FORMAT_LABELS[format] ??
        format.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
    );
}
