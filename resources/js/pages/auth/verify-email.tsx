import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { api } from '@/services/api';
import { useAuthStore } from '@/store/auth';

export default function VerifyEmail() {
    const navigate = useNavigate();
    const logout = useAuthStore((state) => state.logout);
    const [processing, setProcessing] = useState(false);
    const [sent, setSent] = useState(false);

    const handleResend = async () => {
        setProcessing(true);

        try {
            await api.post('/email/verification-notification');
            setSent(true);
        } finally {
            setProcessing(false);
        }
    };

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    return (
        <div className="space-y-6 text-center">
            {sent && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    A new verification link has been sent to the email address you
                    provided during registration.
                </div>
            )}

            <Button disabled={processing} variant="secondary" onClick={handleResend}>
                {processing && <Spinner />}
                Resend verification email
            </Button>

            <button
                type="button"
                onClick={handleLogout}
                className="text-foreground mx-auto block text-sm underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
            >
                Log out
            </button>
        </div>
    );
}
