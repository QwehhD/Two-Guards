import { useState } from 'react';
import type { FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { api, ensureCsrfCookie } from '@/services/api';
import { useAuthStore } from '@/store/auth';

export default function Register() {
    const navigate = useNavigate();
    const setUser = useAuthStore((state) => state.setUser);

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            await ensureCsrfCookie();
            await api.post('/register', {
                name,
                email,
                password,
                password_confirmation: passwordConfirmation,
            });

            const { data: user } = await api.get('/api/me');
            setUser(user);
            navigate('/');
        } catch (error: any) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
            <div className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autoFocus
                        tabIndex={1}
                        autoComplete="name"
                        placeholder="Full name"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                    />
                    <InputError message={errors.name?.[0]} className="mt-2" />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        tabIndex={2}
                        autoComplete="email"
                        placeholder="email@example.com"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                    />
                    <InputError message={errors.email?.[0]} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <PasswordInput
                        id="password"
                        required
                        tabIndex={3}
                        autoComplete="new-password"
                        placeholder="Password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                    />
                    <InputError message={errors.password?.[0]} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Confirm password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        tabIndex={4}
                        autoComplete="new-password"
                        placeholder="Confirm password"
                        value={passwordConfirmation}
                        onChange={(e) => setPasswordConfirmation(e.target.value)}
                    />
                    <InputError message={errors.password_confirmation?.[0]} />
                </div>

                <Button
                    type="submit"
                    className="mt-2 w-full"
                    tabIndex={5}
                    disabled={processing}
                    data-test="register-user-button"
                >
                    {processing && <Spinner />}
                    Create account
                </Button>
            </div>

            <div className="text-muted-foreground text-center text-sm">
                Already have an account?{' '}
                <TextLink to="/login" tabIndex={6}>
                    Log in
                </TextLink>
            </div>
        </form>
    );
}
