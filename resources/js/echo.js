import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Realtime is an enhancement, never the source of truth (§12) — every
 * page that uses window.Echo also polls independently and must keep
 * working with none of this. BROADCAST_CONNECTION defaults to "log" in
 * .env; VITE_BROADCAST_CONNECTION mirrors it so the frontend only ever
 * tries to open a socket when Reverb is actually the active broadcaster
 * (a key can be present in .env without Reverb being turned on — checking
 * the key alone would still try to connect and fail).
 */
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const broadcasterIsReverb = import.meta.env.VITE_BROADCAST_CONNECTION === 'reverb';

if (broadcasterIsReverb && reverbKey) {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
