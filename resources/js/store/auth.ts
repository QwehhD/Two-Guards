import { create } from 'zustand';
import { api, ensureCsrfCookie } from '@/services/api';
import type { User } from '@/types';

type AuthStatus = 'idle' | 'loading' | 'authenticated' | 'guest';

type AuthState = {
    user: User | null;
    status: AuthStatus;
    fetchUser: () => Promise<void>;
    setUser: (user: User) => void;
    logout: () => Promise<void>;
};

export const useAuthStore = create<AuthState>((set) => ({
    user: null,
    status: 'idle',

    fetchUser: async () => {
        set({ status: 'loading' });

        try {
            const { data } = await api.get<User>('/api/me');

            set({ user: data, status: 'authenticated' });
        } catch {
            set({ user: null, status: 'guest' });
        }
    },

    setUser: (user) => set({ user, status: 'authenticated' }),

    logout: async () => {
        await ensureCsrfCookie();
        await api.post('/logout');

        set({ user: null, status: 'guest' });
    },
}));
