import { api } from '@/services/api';
import type { CreateUserPayload, UpdateUserPayload, User } from '@/types';

export async function fetchUsers(): Promise<User[]> {
    const { data } = await api.get<User[]>('/api/users');

    return data;
}

export async function createUser(payload: CreateUserPayload): Promise<User> {
    const { data } = await api.post<User>('/api/users', payload);

    return data;
}

export async function updateUser(id: number, payload: UpdateUserPayload): Promise<User> {
    const { data } = await api.put<User>(`/api/users/${id}`, payload);

    return data;
}

export async function deleteUser(id: number): Promise<void> {
    await api.delete(`/api/users/${id}`);
}
