/**
 * Toast/notification stack (§15) — replaces browser alert() and the old
 * inline session('status') banner. Server-rendered flashes are picked up
 * by components/toast-container.blade.php, which calls RunixToast.show()
 * for each one on page load; call it yourself for client-only messages.
 */

const REGION_ID = 'runix-toast-region';
const DISMISS_MS = 250;

const ICONS = {
    success:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    danger: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
};

const CLOSE_ICON =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

function region() {
    let el = document.getElementById(REGION_ID);

    if (!el) {
        el = document.createElement('div');
        el.id = REGION_ID;
        el.className = 'runix-toast-region';
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        document.body.appendChild(el);
    }

    return el;
}

function dismiss(toast) {
    toast.dataset.visible = 'false';
    window.setTimeout(() => toast.remove(), DISMISS_MS);
}

function show(message, { tone = 'success', duration = 4000 } = {}) {
    if (!message) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = 'runix-toast';
    toast.dataset.tone = tone;
    toast.dataset.visible = 'false';

    const icon = document.createElement('span');
    icon.className = 'runix-toast-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = ICONS[tone] || ICONS.success;

    const text = document.createElement('p');
    text.className = 'runix-toast-message';
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'runix-toast-close';
    // Phase 9 §10 — translated server-side via components/toast-container.blade.php's
    // window.RunixToastI18n; English fallback covers a page that doesn't
    // render that component (no guest flow currently calls show(), but
    // this keeps the function itself language-independent regardless).
    close.setAttribute('aria-label', window.RunixToastI18n?.dismiss ?? 'Dismiss notification');
    close.innerHTML = CLOSE_ICON;
    close.addEventListener('click', () => dismiss(toast));

    toast.append(icon, text, close);
    region().appendChild(toast);

    // Force layout so the opacity/transform transition actually runs.
    requestAnimationFrame(() => requestAnimationFrame(() => {
        toast.dataset.visible = 'true';
    }));

    if (duration > 0) {
        window.setTimeout(() => dismiss(toast), duration);
    }

    return toast;
}

window.RunixToast = { show };

export { show };
