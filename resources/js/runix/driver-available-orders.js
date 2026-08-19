/**
 * Drives the #available-orders-list board: periodic polling (the real
 * source of truth — §12) plus an optional Echo layer that reacts sooner
 * when a broadcast connection happens to be live. Never assumes Echo is
 * present — same shape as resources/js/runix/driver-offers.js, which
 * this board replaces as the driver's primary "find work" page.
 */
(function () {
    const POLL_INTERVAL_MS = 20000;
    const TAKEN_DISPLAY_MS = 2500;
    const container = document.getElementById('available-orders-list');

    if (!container) {
        return;
    }

    function refresh() {
        fetch('/driver/available-orders?partial=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => (response.ok ? response.text() : null))
            .then((html) => {
                if (html !== null) {
                    container.innerHTML = html;
                }
            })
            .catch(() => {
                // Silent — the next poll tick tries again. Polling is
                // never the only way this list can be correct (§12).
            });
    }

    /**
     * Shows a brief "this order was taken" message on the matching card,
     * then refreshes (which drops the card for good, same as every other
     * still-visible-elsewhere-but-now-gone case). Falls straight through
     * to an immediate refresh if the card isn't on this page — it was
     * never one of this driver's cards to begin with.
     *
     * OrderTaken's public payload deliberately carries no driver name
     * (or any other PII) — it's broadcast on `orders.taken`, which
     * anyone can subscribe to without authenticating — so this can only
     * ever show a generic message, never "Taken by <name>".
     */
    function handleTaken(data) {
        const card = data.order_id
            ? container.querySelector(`[data-order-id="${data.order_id}"]`)
            : null;

        if (!card) {
            refresh();

            return;
        }

        const body = card.querySelector('[data-order-card-body]');
        const overlay = card.querySelector('[data-taken-overlay]');

        body?.classList.add('hidden');
        overlay?.classList.remove('hidden');
        overlay?.classList.add('flex');

        setTimeout(refresh, TAKEN_DISPLAY_MS);
    }

    refresh();
    setInterval(refresh, POLL_INTERVAL_MS);

    const driverId = container.dataset.driverId;

    if (window.Echo && driverId) {
        window.Echo.channel('orders.available').listen('.order.available', refresh);
        window.Echo.channel('orders.taken').listen('.order.taken', handleTaken);
    }
})();
