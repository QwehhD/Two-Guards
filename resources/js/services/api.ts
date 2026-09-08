import axios from 'axios';

export const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL ?? window.location.origin,
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
    },
});

/**
 * Sanctum's SPA auth is cookie-based: before any state-changing request
 * (login, register, ...) the frontend must first hit /sanctum/csrf-cookie
 * so the browser gets the XSRF-TOKEN cookie that axios then echoes back
 * as the X-XSRF-TOKEN header.
 */
export async function ensureCsrfCookie(): Promise<void> {
    await api.get('/sanctum/csrf-cookie');
}

api.interceptors.response.use(
    (response) => response,
    (error) => {
        // GET /api/me is the passive "am I logged in?" check that runs on
        // every page load (see useAuthStore.fetchUser), including the
        // public landing page. A 401 from it just means "guest" — that's
        // an expected, normal outcome, not a session that expired mid-use,
        // so it must NOT trigger the same redirect-to-login as a 401 from
        // an actual authenticated action.
        const isIdentityCheck = error.config?.url === '/api/me';

        if (
            error.response?.status === 401 &&
            !isIdentityCheck &&
            window.location.pathname !== '/login'
        ) {
            window.location.href = '/login';
        }

        return Promise.reject(error);
    },
);
