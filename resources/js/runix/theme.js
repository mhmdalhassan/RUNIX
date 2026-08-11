/**
 * Light / Dark / System theme switching (§16).
 *
 * Storage: localStorage['runix-theme'] is 'light', 'dark', or absent
 * (absent = "System", the default — follows prefers-color-scheme via the
 * CSS in resources/css/runix/variables.css). A tiny inline script in each
 * layout's <head> mirrors the `apply()` logic below so the theme is set
 * before first paint and there's no flash of the wrong theme.
 */

const STORAGE_KEY = 'runix-theme';
const root = document.documentElement;

function apply(theme) {
    if (theme === 'dark' || theme === 'light') {
        root.setAttribute('data-theme', theme);
    } else {
        root.removeAttribute('data-theme');
    }
}

function current() {
    const stored = window.localStorage.getItem(STORAGE_KEY);

    return stored === 'dark' || stored === 'light' ? stored : 'system';
}

function set(theme) {
    if (theme === 'system') {
        window.localStorage.removeItem(STORAGE_KEY);
    } else {
        window.localStorage.setItem(STORAGE_KEY, theme);
    }

    apply(theme);
    document.dispatchEvent(new CustomEvent('runix-theme-changed', { detail: { theme } }));
}

function cycle() {
    const order = ['system', 'light', 'dark'];
    const next = order[(order.indexOf(current()) + 1) % order.length];
    set(next);

    return next;
}

apply(current());

window.RunixTheme = { current, set, cycle };

export { current, set, cycle };
