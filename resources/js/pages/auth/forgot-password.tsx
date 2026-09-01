import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { api, ensureCsrfCookie } from '@/services/api';

export default function ForgotPassword() {
    const [email, setEmail] = useState('');
    const [processing, setProcessing] = useState(false);
    const [status, setStatus] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        setErrors({});
        setStatus(null);

        try {
            await ensureCsrfCookie();
            const { data } = await api.post('/forgot-password', { email });
            setStatus(data.status ?? 'We have emailed your password reset link.');
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
            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="off"
                            autoFocus
                            placeholder="email@example.com"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                        />
                        <InputError message={errors.email?.[0]} />
                    </div>

                    <div className="my-6 flex items-center justify-start">
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && <Spinner />}
                            Email password reset link
                        </Button>
                    </div>
                </form>

                <div className="text-muted-foreground space-x-1 text-center text-sm">
                    <span>Or, return to</span>
                    <TextLink to="/login">log in</TextLink>
                </div>
            </div>
        </>
    );
}
