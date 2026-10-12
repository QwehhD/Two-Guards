import { api } from '@/services/api';
import type { Device, DeviceMode } from '@/types';

export async function fetchDevices(): Promise<Device[]> {
    const { data } = await api.get<Device[]>('/api/devices');

    return data;
}

export async function updateDeviceMode(id: number, mode: DeviceMode): Promise<Device> {
    const { data } = await api.patch<Device>(`/api/devices/${id}`, { mode });

    return data;
}
