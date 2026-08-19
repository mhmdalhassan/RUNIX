import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

/**
 * Live driver location on the public order-tracking page
 * (resources/views/track/show.blade.php). Polling against
 * App\Http\Controllers\OrderLocationController is the ONLY update
 * mechanism here (no Echo layer, unlike driver-available-orders.js) —
 * this page is deliberately never handed the order's numeric id or its
 * own tracking_token a second time anywhere in the rendered HTML (see
 * OrderTrackingTest's own "never render an explicit order id"/"token
 * never rendered twice" coverage), so there's nothing to key a
 * client-side Echo channel subscription on. The endpoint URL itself is
 * derived from the page's own address instead of a server-rendered data
 * attribute, for the same reason: `/track/{token}` -> `/track/{token}/location`
 * needs no new disclosure, since the token's already sitting in the
 * address bar the customer is already looking at.
 *
 * The endpoint's own `trackable` flag is what drives showing/hiding the
 * map here, not the order status the page happened to render with at
 * load time — an order that gets accepted, or delivered, while the
 * customer is still sitting on this page updates without them ever
 * reloading it.
 */
(function () {
    const POLL_INTERVAL_MS = 8000;

    const root = document.getElementById('order-tracking-map-root');

    if (!root) {
        return;
    }

    // Leaflet's default marker icon references relative image paths
    // that don't survive a bundler unless re-pointed at the bundled
    // copies — this is the standard fix.
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({ iconRetinaUrl: markerIcon2x, iconUrl: markerIcon, shadowUrl: markerShadow });

    const endpoint = window.location.pathname.replace(/\/+$/, '') + '/location';

    const mapEl = document.getElementById('order-tracking-map');
    const waitingEl = document.getElementById('order-tracking-map-waiting');
    const doneEl = document.getElementById('order-tracking-map-done');
    const metaEl = document.getElementById('order-tracking-map-meta');

    let map = null;
    let marker = null;
    let stopped = false;

    function showOnly(el) {
        [mapEl, waitingEl, doneEl].forEach((candidate) => {
            candidate?.classList.toggle('hidden', candidate !== el);
        });
    }

    /**
     * Intl.RelativeTimeFormat rather than a hand-rolled "Xs/Xm ago"
     * string — it picks the right plural form for the page's own locale
     * (document.documentElement.lang, set by the <html> tag every page
     * already renders) automatically, including Arabic's dual/few/many
     * forms, with no translation strings to maintain here.
     */
    function relativeTime(isoString) {
        if (!isoString) {
            return '';
        }

        const seconds = Math.round((new Date(isoString).getTime() - Date.now()) / 1000);
        const rtf = new Intl.RelativeTimeFormat(document.documentElement.lang || 'en', { numeric: 'auto' });

        if (Math.abs(seconds) < 60) {
            return rtf.format(seconds, 'second');
        }

        return rtf.format(Math.round(seconds / 60), 'minute');
    }

    function ensureMap(latitude, longitude) {
        if (map) {
            return;
        }

        map = L.map(mapEl, { attributionControl: true }).setView([latitude, longitude], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        marker = L.marker([latitude, longitude]).addTo(map);
    }

    function update(data) {
        if (stopped) {
            return;
        }

        if (!data.trackable) {
            showOnly(doneEl);
            stop();

            return;
        }

        if (!data.has_location) {
            showOnly(waitingEl);

            return;
        }

        showOnly(mapEl);
        ensureMap(data.latitude, data.longitude);
        marker.setLatLng([data.latitude, data.longitude]);
        map.panTo([data.latitude, data.longitude]);

        if (metaEl) {
            metaEl.textContent = relativeTime(data.updated_at);
            metaEl.classList.remove('hidden');
        }

        // Leaflet renders into a container that was `hidden` (display:
        // none) a moment ago the very first time a fix arrives — it
        // measured a zero-size box, so its internal layout is wrong
        // until told to re-measure.
        map.invalidateSize();
    }

    function poll() {
        fetch(endpoint, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (data) {
                    update(data);
                }
            })
            .catch(() => {
                // Silent — the next poll tick tries again.
            });
    }

    function stop() {
        stopped = true;
        clearInterval(intervalId);
    }

    poll();
    const intervalId = setInterval(poll, POLL_INTERVAL_MS);
})();
