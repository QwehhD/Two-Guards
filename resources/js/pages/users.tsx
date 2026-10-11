import { AlertCircle, KeyRound, UserPlus } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { ChangePasswordDialog } from '@/components/users/change-password-dialog';
import { CreateUserDialog } from '@/components/users/create-user-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { fetchUsers } from '@/services/userService';
import { useAuthStore } from '@/store/auth';
import type { User, UserRole } from '@/types';

const ROLE_LABELS: Record<UserRole, string> = {
    admin: 'Admin',
    karyawan: 'Karyawan',
};

const COLUMN_COUNT = 4;

function sortByName(users: User[]): User[] {
    return [...users].sort((a, b) => a.name.localeCompare(b.name, 'id'));
}

/**
 * Admin-only account management (the route and /api/users both enforce
 * that). With self-registration and public password reset closed, this is
 * where every account is created and every password is reset.
 */
export default function Users() {
    const currentUser = useAuthStore((state) => state.user);

    const [users, setUsers] = useState<User[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [creating, setCreating] = useState(false);
    // The account whose password is being changed; also the dialog's open state.
    const [changingPasswordFor, setChangingPasswordFor] = useState<User | null>(null);

    const loadUsers = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            setUsers(sortByName(await fetchUsers()));
        } catch {
            setError('Gagal memuat daftar akun.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadUsers();
    }, [loadUsers]);

    return (
        <div className="flex flex-1 flex-col gap-4 p-4">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-xl font-semibold">Manajemen Akun</h1>
                    <p className="text-muted-foreground text-sm">
                        Pendaftaran mandiri dan reset password publik ditutup: akun baru dibuat dan
                        password diganti oleh admin di halaman ini.
                    </p>
                </div>
                <Button onClick={() => setCreating(true)}>
                    <UserPlus /> Tambah Akun
                </Button>
            </div>

            {error && (
                <Alert variant="destructive">
                    <AlertCircle />
                    <AlertTitle>Terjadi kesalahan</AlertTitle>
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="px-3 py-2 font-medium">Nama</th>
                                    <th className="px-3 py-2 font-medium">Email</th>
                                    <th className="px-3 py-2 font-medium">Role</th>
                                    <th className="px-3 py-2 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {loading &&
                                    Array.from({ length: 3 }).map((_, i) => (
                                        <tr key={i} className="border-b last:border-0">
                                            {Array.from({ length: COLUMN_COUNT }).map((__, j) => (
                                                <td key={j} className="px-3 py-3">
                                                    <Skeleton className="h-4 w-full" />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}

                                {!loading && users.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={COLUMN_COUNT}
                                            className="text-muted-foreground px-3 py-8 text-center"
                                        >
                                            Belum ada akun.
                                        </td>
                                    </tr>
                                )}

                                {!loading &&
                                    users.map((user) => (
                                        <tr key={user.id} className="border-b last:border-0">
                                            <td className="px-3 py-3 font-medium">
                                                {user.name}
                                                {user.id === currentUser?.id && (
                                                    <span className="text-muted-foreground font-normal"> (Anda)</span>
                                                )}
                                            </td>
                                            <td className="px-3 py-3">{user.email}</td>
                                            <td className="px-3 py-3">
                                                <Badge variant={user.role === 'admin' ? 'default' : 'secondary'}>
                                                    {ROLE_LABELS[user.role]}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-3">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => setChangingPasswordFor(user)}
                                                >
                                                    <KeyRound /> Ganti Password
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <CreateUserDialog
                open={creating}
                onOpenChange={setCreating}
                onCreated={(user) => setUsers((prev) => sortByName([...prev, user]))}
            />

            <ChangePasswordDialog
                user={changingPasswordFor}
                open={changingPasswordFor !== null}
                onOpenChange={(open) => {
                    if (!open) setChangingPasswordFor(null);
                }}
            />
        </div>
    );
}
