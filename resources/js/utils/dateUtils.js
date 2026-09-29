/**
 * TrustNode Date & Time Formatting Utilities
 * Handles NULL, missing, or invalid dates cleanly without falling back to Unix epoch (1970).
 */

/**
 * Format relative time safely (e.g. "12s ago", "5m ago", "2h 15m ago", "Yesterday, 4:32 PM")
 * Returns "—" for null/undefined/invalid values.
 * Returns nullLabel or "—" if timestamp is missing.
 */
export function formatRelativeTime(dateValue, nullLabel = '—') {
    if (dateValue === null || dateValue === undefined || dateValue === '') {
        return nullLabel;
    }

    const date = new Date(dateValue);
    const timeMs = date.getTime();

    if (isNaN(timeMs) || timeMs <= 0) {
        return nullLabel;
    }

    const now = Date.now();
    const diffSeconds = Math.max(0, Math.floor((now - timeMs) / 1000));

    if (diffSeconds < 5) return 'Just now';
    if (diffSeconds < 60) return `${diffSeconds}s ago`;

    const minutes = Math.floor(diffSeconds / 60);
    if (minutes < 60) return `${minutes}m ago`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
        const remMins = minutes % 60;
        return remMins > 0 ? `${hours}h ${remMins}m ago` : `${hours}h ago`;
    }

    const days = Math.floor(hours / 24);
    if (days === 1) {
        return `Yesterday, ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
    }

    if (days < 7) {
        return `${days}d ago`;
    }

    return date.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
}

/**
 * Format absolute datetime safely (e.g., "Sep 28, 2026, 2:14:12 AM" or "Today, 02:14:12 AM")
 * Returns "—" for null/undefined/invalid values.
 */
export function formatHumanDateTime(dateValue, nullLabel = '—') {
    if (dateValue === null || dateValue === undefined || dateValue === '') {
        return nullLabel;
    }

    const date = new Date(dateValue);
    const timeMs = date.getTime();

    if (isNaN(timeMs) || timeMs <= 0) {
        return nullLabel;
    }

    const today = new Date();
    const isToday = date.toDateString() === today.toDateString();
    const timeStr = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    if (isToday) {
        return `Today, ${timeStr}`;
    }

    return `${date.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' })}, ${timeStr}`;
}
