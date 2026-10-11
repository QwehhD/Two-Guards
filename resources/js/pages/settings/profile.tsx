import { useState } from 'react';
import type { FormEvent } from 'react';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { api } from '@/services/api';
import { useAuthStore } from '@/store/auth';

export default function Profile() {
    const user = useAuthStore((state) => state.user);
    const setUser = useAuthStore((state) => state.setUser);

    const [name, setName] = useState(user?.name ?? '');
    const [email, setEmail] = useState(user?.email ?? '');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [saved, setSaved] = useState(false);

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        setErrors({});
        setSaved(false);

        try {
            const { data } = await api.patch('/api/profile', { name, email });
            setUser(data);
            setSaved(true);
        } catch (error: any) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <>
            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your name and email address"
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>

                        <Input
                            id="name"
                            className="mt-1 block w-full"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            required
                            autoComplete="name"
                            placeholder="Full name"
                        />

                        <InputError className="mt-2" message={errors.name?.[0]} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>

                        <Input
                            id="email"
                            type="email"
                            className="mt-1 block w-full"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                            autoComplete="username"
                            placeholder="Email address"
                        />

                        <InputError className="mt-2" message={errors.email?.[0]} />
                    </div>

                    <div className="flex items-center gap-4">
                        <Button disabled={processing} data-test="update-profile-button">
                            Save
                        </Button>

                        {saved && (
                            <p className="text-sm text-neutral-600 dark:text-neutral-400">
                                Saved.
                            </p>
                        )}
                    </div>
                </form>
            </div>

            <DeleteUser />
        </>
    );
}
