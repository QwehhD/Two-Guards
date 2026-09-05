export type DeviceMode = 'auto' | 'manual';

export type DeviceStatus = 'online' | 'offline';

// Mirrors the fields returned by GET /api/devices (id, name, mode, status only
// — that endpoint is a minimal picker source, not full device management).
export type Device = {
    id: number;
    name: string;
    mode: DeviceMode;
    status: DeviceStatus;
};
