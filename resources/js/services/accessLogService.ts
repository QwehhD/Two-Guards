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
