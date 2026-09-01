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
        if (error.response?.status === 401 && window.location.pathname !== '/login') {
            window.location.href = '/login';
        }

        return Promise.reject(error);
    },
);
