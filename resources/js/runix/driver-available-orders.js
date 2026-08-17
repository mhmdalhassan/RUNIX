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
     * Shows "Taken by <name>" on the matching card for a moment, then
     * refreshes (which drops the card for good, same as every other
     * still-visible-elsewhere-but-now-gone case). Falls straight through
     * to an immediate refresh if the card isn't on this page or no name
     * was given — e.g. it was never one of this driver's cards to begin
     * with, or the event fired without one (OrderTaken.driverName is
     * nullable).
     */
    function handleTaken(data) {
        const card = data.order_id
            ? container.querySelector(`[data-order-id="${data.order_id}"]`)
            : null;

        if (!card || !data.driver_name) {
            refresh();

            return;
        }

        const body = card.querySelector('[data-order-card-body]');
        const overlay = card.querySelector('[data-taken-overlay]');
        const message = card.querySelector('[data-taken-message]');
        const template = card.dataset.takenTemplate || 'Taken by :name';

        if (message) {
            message.textContent = template.replace(':name', data.driver_name);
        }

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
