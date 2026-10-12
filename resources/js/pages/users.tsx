import { AlertCircle, KeyRound, Trash2, UserPlus } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { ChangePasswordDialog } from '@/components/users/change-password-dialog';
import { CreateUserDialog } from '@/components/users/create-user-dialog';
import { DeleteUserDialog } from '@/components/users/delete-user-dialog';
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
 * that). With self-registration, self-deletion and public password reset
 * closed, this is where every account is created, deleted, and has its
 * password reset.
 */
export default function Users() {
    const currentUser = useAuthStore((state) => state.user);

    const [users, setUsers] = useState<User[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [creating, setCreating] = useState(false);
    // The account whose password is being changed; also the dialog's open state.
    const [changingPasswordFor, setChangingPasswordFor] = useState<User | null>(null);
    // The account about to be deleted; also the confirmation dialog's open state.
    const [deleting, setDeleting] = useState<User | null>(null);

    // Mirrors the API's rules so the page never offers what it would refuse:
    // nobody deletes their own account, and the last admin always stays.
    const adminCount = users.filter((user) => user.role === 'admin').length;
    const deleteBlockedReason = (user: User): string | null => {
        if (user.id === currentUser?.id) return 'Akun sendiri tidak bisa dihapus.';
        if (user.role === 'admin' && adminCount === 1) return 'Admin terakhir tidak bisa dihapus.';

        return null;
    };

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
            <PageHeader
                eyebrow="Admin"
                title="Manajemen Akun"
                description="Pendaftaran mandiri, hapus akun sendiri, dan reset password publik ditutup: akun dibuat, dihapus, dan password-nya diganti oleh admin di halaman ini."
                actions={
                    <Button onClick={() => setCreating(true)}>
                        <UserPlus /> Tambah Akun
                    </Button>
                }
            />

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
                                <tr className="label-mono border-b text-left text-muted-foreground">
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
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => setChangingPasswordFor(user)}
                                                    >
                                                        <KeyRound /> Ganti Password
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="text-destructive"
                                                        disabled={deleteBlockedReason(user) !== null}
                                                        title={deleteBlockedReason(user) ?? undefined}
                                                        onClick={() => setDeleting(user)}
                                                    >
                                                        <Trash2 /> Hapus
                                                    </Button>
                                                </div>
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

            <DeleteUserDialog
                user={deleting}
                open={deleting !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleting(null);
                }}
                onDeleted={(deleted) => setUsers((prev) => prev.filter((user) => user.id !== deleted.id))}
            />
        </div>
    );
}
