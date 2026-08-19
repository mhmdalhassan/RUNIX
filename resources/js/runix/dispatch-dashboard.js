/**
 * Drives the #dispatch-board container: periodic polling (the real
 * source of truth — §12) plus an optional Echo layer that reacts sooner
 * when a broadcast connection happens to be live. Same shape as
 * resources/js/runix/driver-available-orders.js — the event is only ever
 * a "go refresh" hint, never trusted as the board's actual state, so
 * every listener below just schedules the same full re-fetch rather than
 * patching the DOM from the event payload.
 *
 * scheduleRefresh() debounces: OrderAvailable/OrderTaken/
 * DispatchActivityUpdated can all fire within milliseconds of each other
 * (e.g. one claim cancels several other pending offers and updates the
 * activity feed in the same request) — coalescing them into one fetch
 * avoids a burst of overlapping requests for what is, a moment later,
 * the same authoritative state anyway.
 */
(function () {
    const POLL_INTERVAL_MS = 20000;
    const DEBOUNCE_MS = 400;
    const container = document.getElementById('dispatch-board');

    if (!container) {
        return;
    }

    let debounceTimer = null;

    function refresh() {
        fetch('/dispatch/dashboard?partial=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => (response.ok ? response.text() : null))
            .then((html) => {
                if (html !== null) {
                    container.innerHTML = html;
                }
            })
            .catch(() => {
                // Silent — the next poll tick tries again. Polling is
                // never the only way this board can be correct (§12), and
                // a failed fetch (Reverb down, network blip) leaves the
                // last-known-good render in place rather than clearing it.
            });
    }

    function scheduleRefresh() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refresh, DEBOUNCE_MS);
    }

    setInterval(refresh, POLL_INTERVAL_MS);

    if (window.Echo) {
        window.Echo.channel('orders.available').listen('.order.available', scheduleRefresh);
        window.Echo.channel('orders.taken').listen('.order.taken', scheduleRefresh);
        window.Echo.private('admin.dispatch').listen('.dispatch.activity', scheduleRefresh);
    }
})();
