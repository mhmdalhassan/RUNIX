/**
 * Phase 5 §1/§5. Watches the driver's browser-reported GPS position while
 * they're online and PATCHes it to the server, throttled. Guards on the
 * data-driver-location-tracking attribute set by components/app-shell.blade.php
 * (driver role only) — same "no container, do nothing" pattern as
 * driver-offers.js. Never runs while offline: watchPosition is only
 * started when data-driver-online="1", and is stopped immediately if the
 * page reloads into an offline state (a fresh watch never starts).
 */
(function () {
    const THROTTLE_MS = 60000;
    const THROTTLE_DISTANCE_METERS = 50;

    const root = document.querySelector('[data-driver-location-tracking]');

    if (!root || root.dataset.driverOnline !== '1') {
        return;
    }

    if (!('geolocation' in navigator)) {
        return;
    }

    const endpoint = root.dataset.locationEndpoint;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!endpoint || !csrfToken) {
        return;
    }

    let lastSentAt = 0;
    let lastSentCoords = null;

    // Coarse client-side distance check for the throttle only — doesn't
    // need survey-grade precision, just "did we move meaningfully."
    function metersBetween(a, b) {
        const R = 6371000;
        const dLat = ((b.latitude - a.latitude) * Math.PI) / 180;
        const dLon = ((b.longitude - a.longitude) * Math.PI) / 180;
        const lat1 = (a.latitude * Math.PI) / 180;
        const lat2 = (b.latitude * Math.PI) / 180;

        const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2;

        return 2 * R * Math.asin(Math.sqrt(h));
    }

    function shouldSend(coords) {
        const now = Date.now();

        if (now - lastSentAt >= THROTTLE_MS) {
            return true;
        }

        if (lastSentCoords && metersBetween(lastSentCoords, coords) >= THROTTLE_DISTANCE_METERS) {
            return true;
        }

        return lastSentCoords === null;
    }

    function send(position) {
        const coords = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
        };

        if (!shouldSend(coords)) {
            return;
        }

        lastSentAt = Date.now();
        lastSentCoords = coords;

        fetch(endpoint, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                latitude: coords.latitude,
                longitude: coords.longitude,
                accuracy: position.coords.accuracy,
            }),
        }).catch(() => {
            // Silent — the next watchPosition callback tries again.
            // Location sharing is best-effort, never a blocking action.
        });
    }

    navigator.geolocation.watchPosition(send, () => {
        // Permission denied / unavailable — the driver stays online and
        // fully eligible for offers regardless (§5); nothing to do here.
    }, {
        enableHighAccuracy: false,
        maximumAge: 30000,
    });
})();
