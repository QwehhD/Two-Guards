import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { deleteUser } from '@/services/userService';
import type { User } from '@/types';

type Props = {
    /** The account to delete; null while closed. */
    user: User | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: (user: User) => void;
};

/**
 * Confirms deleting an account. Only an admin deletes accounts, never their
 * own and never the last admin — the API refuses those, and the Akun page
 * doesn't offer them.
 */
export function DeleteUserDialog({ user, open, onOpenChange, onDeleted }: Props) {
    const [processing, setProcessing] = useState(false);

    const handleDelete = async () => {
        if (!user) return;

        setProcessing(true);

        try {
            await deleteUser(user.id);
            toast.success(`Akun ${user.name} dihapus.`);
            onDeleted?.(user);
            onOpenChange(false);
        } catch (error: any) {
            toast.error(error.response?.data?.message ?? 'Gagal menghapus akun. Silakan coba lagi.');
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hapus Akun</DialogTitle>
                    <DialogDescription>
                        Hapus akun <span className="font-medium">{user?.name}</span> ({user?.email})?
                        Riwayat akses yang pernah ia proses tetap tersimpan, tetapi namanya tidak lagi
                        tercatat sebagai pemroses.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Batal</Button>
                    </DialogClose>
                    <Button variant="destructive" disabled={processing} onClick={handleDelete}>
                        {processing && <Spinner />}
                        Hapus Akun
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
