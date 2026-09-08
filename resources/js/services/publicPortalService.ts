import { api } from '@/services/api';
import type { PublicAccessLog, PublicDeviceStatus } from '@/types';

/**
 * Fetches the landing page's public, unauthenticated device snapshot from
 * GET /api/public/portal-status. No `withCredentials`-driven auth is
 * required — this call succeeds identically for guests and staff.
 */
export async function fetchPortalStatus(): Promise<PublicDeviceStatus[]> {
    const { data } = await api.get<{ data: PublicDeviceStatus[] }>(
        '/api/public/portal-status',
    );

    return data.data;
}

/**
 * Fetches the landing page's public "recent activity" feed from
 * GET /api/public/recent-activity — already capped to the last 5 entries
 * server-side, and already stripped of anything that could identify a
 * person (see PublicAccessLogResource).
 */
export async function fetchRecentActivity(): Promise<PublicAccessLog[]> {
    const { data } = await api.get<{ data: PublicAccessLog[] }>(
        '/api/public/recent-activity',
    );

    return data.data;
}
