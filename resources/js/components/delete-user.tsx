import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { api } from '@/services/api';
import { useAuthStore } from '@/store/auth';

export default function DeleteUser() {
    const navigate = useNavigate();
    const passwordInput = useRef<HTMLInputElement>(null);
    const [password, setPassword] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [open, setOpen] = useState(false);

    const reset = () => {
        setPassword('');
        setErrors({});
    };

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setProcessing(true);

        try {
            await api.delete('/api/profile', { data: { password } });

            useAuthStore.setState({ user: null, status: 'guest' });
            navigate('/login');
        } catch (error: any) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
            }

            passwordInput.current?.focus();
        } finally {
            setProcessing(false);
        }
    };

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Delete account"
                description="Delete your account and all of its resources"
            />
            <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                    <p className="font-medium">Warning</p>
                    <p className="text-sm">
                        Please proceed with caution, this cannot be undone.
                    </p>
                </div>

                <Dialog
                    open={open}
                    onOpenChange={(value) => {
                        setOpen(value);
                        if (!value) reset();
                    }}
                >
                    <DialogTrigger asChild>
                        <Button variant="destructive" data-test="delete-user-button">
                            Delete account
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Are you sure you want to delete your account?
                        </DialogTitle>
                        <DialogDescription>
                            Once your account is deleted, all of its resources and
                            data will also be permanently deleted. Please enter your
                            password to confirm you would like to permanently delete
                            your account.
                        </DialogDescription>

                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="password" className="sr-only">
                                    Password
                                </Label>

                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    placeholder="Password"
                                    autoComplete="current-password"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                />

                                <InputError message={errors.password?.[0]} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary" onClick={reset}>
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    data-test="confirm-delete-user-button"
                                >
                                    Delete account
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}
