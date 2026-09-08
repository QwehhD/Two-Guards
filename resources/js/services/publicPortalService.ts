import { api } from '@/services/api';
import type { PublicDeviceStatus } from '@/types';

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
