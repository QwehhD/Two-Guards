import Echo from 'laravel-echo';
import Pusher, { type AuthorizerGenerator } from 'pusher-js';
import { api } from '@/services/api';

/**
 * Reverb speaks the same wire protocol as Pusher Channels, so Echo's
 * existing Pusher connector works against it unchanged — only the
 * connection details (host/port/scheme, all VITE_REVERB_* below) point
 * at our own Reverb server instead of Pusher's cloud.
 *
 * Passed via `Pusher:` on the Echo options (laravel-echo v2 tries this
 * before falling back to a global `window.Pusher`), so nothing needs to
 * be attached to `window`.
 */

/**
 * Private-channel authorization. Deliberately reuses `api` — the same
 * axios instance (same `withCredentials`/`withXSRFToken`, same Sanctum
 * session cookie) every other authenticated request in the app already
 * uses — instead of a separate auth mechanism. A user only ever reaches
 * a page that subscribes to a private channel after logging in through
 * the normal Fortify session flow, so that cookie already exists by the
 * time Echo needs to authorize a subscription.
 */
const authorizer: AuthorizerGenerator = (channel) => ({
    authorize(socketId, callback) {
        api
            .post('/broadcasting/auth', {
                socket_id: socketId,
                channel_name: channel.name,
            })
            .then((response) => callback(null, response.data))
            .catch((error) => callback(error, null));
    },
});

const reverbPort = Number(import.meta.env.VITE_REVERB_PORT ?? 443);

// REVERB_SCHEME is "http" for local dev (see .env.example) and "https"
// once Reverb sits behind TLS in production — forceTLS/enabledTransports
// follow it so local dev doesn't try (and fail) to open a wss:// socket
// against a plain ws:// Reverb server.
const useTLS = (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https';

export const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: useTLS,
    enabledTransports: useTLS ? ['ws', 'wss'] : ['ws'],
    Pusher,
    authorizer,
});
