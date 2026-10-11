import type { UserRole } from './auth';

// The role starts out unchosen ('') on purpose: users.role has no default,
// so whoever creates an account has to pick one, and the backend rejects ''.
export type CreateUserPayload = {
    name: string;
    email: string;
    role: UserRole | '';
    password: string;
    password_confirmation: string;
};

// PUT /api/users/{id} always needs name, email and role; the password pair
// is only sent when it should change.
export type UpdateUserPayload = {
    name: string;
    email: string;
    role: UserRole;
    password?: string;
    password_confirmation?: string;
};
