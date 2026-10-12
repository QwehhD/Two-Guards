import type { PortalStatus } from './portal-status';

export type DeviceMode = 'auto' | 'manual';

export type DeviceStatus = 'online' | 'offline';

// Mirrors GET /api/devices (and PATCH /api/devices/{id}): what the
// dashboard's device cards show. The simulate-scan picker only uses id and name.
export type Device = {
    id: number;
    name: string;
    mode: DeviceMode;
    status: DeviceStatus;
    portal_status: PortalStatus;
    last_seen_at: string | null;
};
