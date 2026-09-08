import type { DeviceMode, DeviceStatus } from './device';

export type PortalStatus = 'open' | 'closed';

// Mirrors App\Http\Controllers\Api\PublicPortalStatusController — the
// public landing page's per-device snapshot from GET /api/public/portal-status.
// Deliberately has no id/UID/anything access_logs-derived; see that
// controller for why.
export type PublicDeviceStatus = {
    name: string;
    status: DeviceStatus;
    portal_status: PortalStatus;
    mode: DeviceMode;
};
