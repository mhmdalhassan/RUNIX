/**
 * Drives the #offer-list container: per-card countdown timers, periodic
 * polling (the real source of truth — §12), and an optional Echo layer on
 * top that just triggers an earlier refresh when a broadcast connection
 * happens to be live. Never assumes Echo is present.
 */
(function () {
    const POLL_INTERVAL_MS = 20000;
    const container = document.getElementById('offer-list');

    if (!container) {
        return;
    }

    function startCountdowns() {
        container.querySelectorAll('[data-offer-expires]').forEach((card) => {
            if (card.dataset.countdownStarted) {
                return;
            }

            card.dataset.countdownStarted = '1';

            const expiresAt = new Date(card.dataset.offerExpires).getTime();
            const textEl = card.querySelector('[data-offer-countdown-text]');

            const tick = () => {
                const remaining = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));

                if (textEl) {
                    const minutes = Math.floor(remaining / 60);
                    const seconds = remaining % 60;
                    textEl.textContent = `${minutes}:${String(seconds).padStart(2, '0')}`;
                }

                if (remaining <= 0) {
                    clearInterval(interval);
                    refresh();
                }
            };

            tick();
            const interval = setInterval(tick, 1000);
        });
    }

    function refresh() {
        fetch('/driver/offers?partial=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => (response.ok ? response.text() : null))
            .then((html) => {
                if (html === null) {
                    return;
                }

                container.innerHTML = html;
                startCountdowns();
            })
            .catch(() => {
                // Silent — the next poll tick tries again. Polling is
                // never the only way this list can be correct (§12).
            });
    }

    startCountdowns();
    setInterval(refresh, POLL_INTERVAL_MS);

    const driverId = container.dataset.driverId;

    if (window.Echo && driverId) {
        window.Echo.private(`driver.${driverId}`).listen('.order.offer-created', refresh);
        window.Echo.channel('orders.taken').listen('.order.taken', refresh);
    }
})();
