import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { updateUser } from '@/services/userService';
import type { User } from '@/types';

type Props = {
    /** The account whose password is being changed; null while closed. */
    user: User | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Lets an admin set a new password for any account. This is the only way a
 * password gets reset: public "forgot password" is off while the mail
 * driver is still "log".
 */
export function ChangePasswordDialog({ user, open, onOpenChange }: Props) {
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    // Start from an empty form each time, never with the previous account's input.
    useEffect(() => {
        if (open) {
            setPassword('');
            setPasswordConfirmation('');
            setErrors({});
        }
    }, [open]);

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();

        if (!user) return;

        setProcessing(true);
        setErrors({});

        try {
            // PUT /api/users/{id} also validates name, email and role, so
            // they are sent back unchanged alongside the new password.
            await updateUser(user.id, {
                name: user.name,
                email: user.email,
                role: user.role,
                password,
                password_confirmation: passwordConfirmation,
            });
            toast.success(`Password ${user.name} berhasil diganti.`);
            onOpenChange(false);
        } catch (error: any) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
            } else {
                toast.error('Gagal mengganti password. Silakan coba lagi.');
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ganti Password</DialogTitle>
                    <DialogDescription>
                        Password baru untuk <span className="font-medium">{user?.name}</span> ({user?.email}).
                        Sampaikan password ini langsung ke pemilik akun.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="new-password">Password baru</Label>
                        <PasswordInput
                            id="new-password"
                            required
                            autoFocus
                            autoComplete="new-password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                        />
                        <InputError message={errors.password?.[0]} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="new-password-confirmation">Ulangi password baru</Label>
                        <PasswordInput
                            id="new-password-confirmation"
                            required
                            autoComplete="new-password"
                            value={passwordConfirmation}
                            onChange={(e) => setPasswordConfirmation(e.target.value)}
                        />
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Simpan Password
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
