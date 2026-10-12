import { api } from '@/services/api';
import type { CreateRfidCardPayload, RfidCard, UpdateRfidCardPayload } from '@/types';

export async function fetchRfidCards(): Promise<RfidCard[]> {
    const { data } = await api.get<RfidCard[]>('/api/rfid-cards');

    return data;
}

export async function createRfidCard(payload: CreateRfidCardPayload): Promise<RfidCard> {
    const { data } = await api.post<RfidCard>('/api/rfid-cards', payload);

    return data;
}

export async function updateRfidCard(id: number, payload: UpdateRfidCardPayload): Promise<RfidCard> {
    const { data } = await api.put<RfidCard>(`/api/rfid-cards/${id}`, payload);

    return data;
}

export async function deleteRfidCard(id: number): Promise<void> {
    await api.delete(`/api/rfid-cards/${id}`);
}
