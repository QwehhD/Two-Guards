import { useState } from 'react';
import type { FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { api, ensureCsrfCookie } from '@/services/api';
import { useAuthStore } from '@/store/auth';

export default function Login() {
    const navigate = useNavigate();
    const setUser = useAuthStore((state) => state.setUser);

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            await ensureCsrfCookie();
            await api.post('/login', { email, password, remember });

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
                    <Label htmlFor="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autoFocus
                        tabIndex={1}
                        autoComplete="email"
                        placeholder="email@example.com"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                    />
                    <InputError message={errors.email?.[0]} />
                </div>

                <div className="grid gap-2">
                    <div className="flex items-center">
                        <Label htmlFor="password">Password</Label>
                        <TextLink to="/forgot-password" className="ml-auto text-sm" tabIndex={5}>
                            Forgot your password?
                        </TextLink>
                    </div>
                    <PasswordInput
                        id="password"
                        required
                        tabIndex={2}
                        autoComplete="current-password"
                        placeholder="Password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                    />
                    <InputError message={errors.password?.[0]} />
                </div>

                <div className="flex items-center space-x-3">
                    <Checkbox
                        id="remember"
                        tabIndex={3}
                        checked={remember}
                        onCheckedChange={(checked) => setRemember(checked === true)}
                    />
                    <Label htmlFor="remember">Remember me</Label>
                </div>

                <Button
                    type="submit"
                    className="mt-4 w-full"
                    tabIndex={4}
                    disabled={processing}
                    data-test="login-button"
                >
                    {processing && <Spinner />}
                    Log in
                </Button>
            </div>

            <div className="text-muted-foreground text-center text-sm">
                Don't have an account?{' '}
                <TextLink to="/register" tabIndex={5}>
                    Sign up
                </TextLink>
            </div>
        </form>
    );
}
