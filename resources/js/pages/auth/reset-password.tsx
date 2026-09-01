import { useState } from 'react';
import type { FormEvent } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { api, ensureCsrfCookie } from '@/services/api';

export default function ResetPassword() {
    const navigate = useNavigate();
    const { token } = useParams<{ token: string }>();
    const [searchParams] = useSearchParams();
    const email = searchParams.get('email') ?? '';

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
            await api.post('/reset-password', {
                token,
                email,
                password,
                password_confirmation: passwordConfirmation,
            });

            navigate('/login');
        } catch (error: any) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="email"
                        value={email}
                        className="mt-1 block w-full"
                        readOnly
                    />
                    <InputError message={errors.email?.[0]} className="mt-2" />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <PasswordInput
                        id="password"
                        autoComplete="new-password"
                        className="mt-1 block w-full"
                        autoFocus
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
                        autoComplete="new-password"
                        className="mt-1 block w-full"
                        placeholder="Confirm password"
                        value={passwordConfirmation}
                        onChange={(e) => setPasswordConfirmation(e.target.value)}
                    />
                    <InputError message={errors.password_confirmation?.[0]} className="mt-2" />
                </div>

                <Button
                    type="submit"
                    className="mt-4 w-full"
                    disabled={processing}
                    data-test="reset-password-button"
                >
                    {processing && <Spinner />}
                    Reset password
                </Button>
            </div>
        </form>
    );
}
