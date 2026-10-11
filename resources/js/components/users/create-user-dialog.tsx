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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { createUser } from '@/services/userService';
import type { User, UserRole } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreated?: (user: User) => void;
};

/**
 * Creates an admin or karyawan account. With public registration closed,
 * this is how every new account comes into existence.
 */
export function CreateUserDialog({ open, onOpenChange, onCreated }: Props) {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    // No preselected role: a karyawan may approve opening the gate, so the
    // admin has to choose it on purpose.
    const [role, setRole] = useState<UserRole | ''>('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    useEffect(() => {
        if (open) {
            setName('');
            setEmail('');
            setRole('');
            setPassword('');
            setPasswordConfirmation('');
            setErrors({});
        }
    }, [open]);

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const user = await createUser({
                name,
                email,
                role,
                password,
                password_confirmation: passwordConfirmation,
            });
            toast.success(`Akun ${user.name} berhasil dibuat.`);
            onCreated?.(user);
            onOpenChange(false);
        } catch (error: any) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
            } else {
                toast.error('Gagal membuat akun. Silakan coba lagi.');
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tambah Akun</DialogTitle>
                    <DialogDescription>
                        Akun baru hanya bisa dibuat di sini. Sampaikan password awalnya langsung ke
                        pemilik akun.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="user-name">Nama</Label>
                        <Input
                            id="user-name"
                            required
                            autoFocus
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                        />
                        <InputError message={errors.name?.[0]} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="user-email">Email</Label>
                        <Input
                            id="user-email"
                            type="email"
                            required
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                        />
                        <InputError message={errors.email?.[0]} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="user-role">Role</Label>
                        <Select value={role} onValueChange={(value) => setRole(value as UserRole)}>
                            <SelectTrigger id="user-role" className="w-full">
                                <SelectValue placeholder="Pilih role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="karyawan">Karyawan</SelectItem>
                                <SelectItem value="admin">Admin</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.role?.[0]} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="user-password">Password awal</Label>
                        <PasswordInput
                            id="user-password"
                            required
                            autoComplete="new-password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                        />
                        <InputError message={errors.password?.[0]} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="user-password-confirmation">Ulangi password</Label>
                        <PasswordInput
                            id="user-password-confirmation"
                            required
                            autoComplete="new-password"
                            value={passwordConfirmation}
                            onChange={(e) => setPasswordConfirmation(e.target.value)}
                        />
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Buat Akun
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
