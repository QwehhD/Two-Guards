import { api } from '@/services/api';
import type { CreateRfidCardPayload, RfidCard } from '@/types';

export async function createRfidCard(payload: CreateRfidCardPayload): Promise<RfidCard> {
    const { data } = await api.post<RfidCard>('/api/rfid-cards', payload);

    return data;
}
