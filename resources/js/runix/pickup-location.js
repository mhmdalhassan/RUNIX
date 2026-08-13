/**
 * Phase 6 §2/§3/§7. Lets a dispatcher set an order's pickup coordinates
 * from their own browser location instead of typing lat/lng by hand.
 * Guards on [data-pickup-location] (present on the order create/edit
 * forms — see resources/views/admin/orders/partials/pickup-location.blade.php)
 * — same "no container, do nothing" pattern as driver-location.js and
 * driver-offers.js.
 *
 * Deliberately a one-shot getCurrentPosition() on an explicit click, not
 * a watchPosition() — this is a manual "capture where I am right now"
 * action, not continuous tracking (that's driver-location.js's job, and
 * only for drivers). Never submits the form itself; the dispatcher still
 * has to click Create/Save.
 */
(function () {
    document.querySelectorAll('[data-pickup-location]').forEach((root) => {
        const trigger = root.querySelector('[data-pickup-location-trigger]');
        const latitudeInput = root.querySelector('[data-pickup-latitude-input]');
        const longitudeInput = root.querySelector('[data-pickup-longitude-input]');
        const status = root.querySelector('[data-pickup-location-status]');

        if (!trigger || !latitudeInput || !longitudeInput || !status) {
            return;
        }

        if (!('geolocation' in navigator)) {
            trigger.disabled = true;
            status.textContent = 'Geolocation is not supported by this browser.';

            return;
        }

        trigger.addEventListener('click', () => {
            trigger.disabled = true;
            status.textContent = 'Getting your location…';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    trigger.disabled = false;

                    const latitude = position.coords.latitude.toFixed(7);
                    const longitude = position.coords.longitude.toFixed(7);

                    latitudeInput.value = latitude;
                    longitudeInput.value = longitude;
                    status.textContent = `Location selected: ${latitude}, ${longitude}`;
                },
                (error) => {
                    trigger.disabled = false;

                    // Permission denied / position unavailable / timeout —
                    // stay graceful either way (spec §7). The pickup
                    // address text field is still there; coordinates are
                    // an enhancement, never a requirement to submit.
                    status.textContent = error.code === error.PERMISSION_DENIED
                        ? 'Location permission was denied. You can still submit with the address only.'
                        : 'Could not determine your location. You can still submit with the address only.';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                },
            );
        });
    });
})();
