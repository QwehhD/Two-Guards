import { api } from '@/services/api';
import type { AccessLog, AccessLogFilters, PaginatedResponse } from '@/types';

export async function fetchAccessLogs(
    filters: AccessLogFilters = {},
): Promise<PaginatedResponse<AccessLog>> {
    const { data } = await api.get<PaginatedResponse<AccessLog>>('/api/access-logs', {
        params: filters,
    });

    return data;
}

// Thin wrapper around fetchAccessLogs for the Approvals page (Tahap 6). A
// generous per_page keeps every currently-pending scan on one page — in
// practice there should only ever be a handful waiting at once.
export async function fetchPendingAccessLogs(): Promise<PaginatedResponse<AccessLog>> {
    return fetchAccessLogs({ status: 'pending', per_page: 100 });
}

export async function approveAccessLog(id: number): Promise<AccessLog> {
    const { data } = await api.post<AccessLog>(`/api/access-logs/${id}/approve`);

    return data;
}

export async function rejectAccessLog(id: number): Promise<AccessLog> {
    const { data } = await api.post<AccessLog>(`/api/access-logs/${id}/reject`);

    return data;
}

export type SimulateScanPayload = {
    device_id: number;
    // Left empty/omitted to simulate an unknown/unregistered card; the
    // backend generates a random UID in that case.
    uid?: string;
};

/**
 * DEVELOPMENT-ONLY (Tahap 6): calls the simulate-scan endpoint, which
 * itself 404s outside local/testing environments. Lets the manual-approval
 * flow be tested before the real ESP32/MQTT listener exists (Tahap 9).
 */
export async function simulateScan(payload: SimulateScanPayload): Promise<AccessLog> {
    const { data } = await api.post<AccessLog>('/api/access-logs/simulate-scan', payload);

    return data;
}
