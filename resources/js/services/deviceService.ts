import { api } from '@/services/api';
import type { Device } from '@/types';

export async function fetchDevices(): Promise<Device[]> {
    const { data } = await api.get<Device[]>('/api/devices');

    return data;
}
