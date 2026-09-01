import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import ForgotPassword from '@/pages/auth/forgot-password';
import Login from '@/pages/auth/login';
import Register from '@/pages/auth/register';
import ResetPassword from '@/pages/auth/reset-password';
import VerifyEmail from '@/pages/auth/verify-email';
import Dashboard from '@/pages/dashboard';
import Appearance from '@/pages/settings/appearance';
import Profile from '@/pages/settings/profile';
import { useAuthStore } from '@/store/auth';

function ProtectedRoute({ children }: { children: ReactNode }) {
    const status = useAuthStore((state) => state.status);

    if (status === 'idle' || status === 'loading') {
        return null;
    }

    if (status === 'guest') {
        return <Navigate to="/login" replace />;
    }

    return <>{children}</>;
}

function GuestOnlyRoute({ children }: { children: ReactNode }) {
    const status = useAuthStore((state) => state.status);

    if (status === 'idle' || status === 'loading') {
        return null;
    }

    if (status === 'authenticated') {
        return <Navigate to="/" replace />;
    }

    return <>{children}</>;
}

export default function App() {
    const fetchUser = useAuthStore((state) => state.fetchUser);

    useEffect(() => {
        void fetchUser();
    }, [fetchUser]);

    return (
        <Routes>
            <Route
                path="/login"
                element={
                    <GuestOnlyRoute>
                        <AuthLayout
                            title="Log in to your account"
                            description="Enter your email and password below to log in"
                        >
                            <Login />
                        </AuthLayout>
                    </GuestOnlyRoute>
                }
            />
            <Route
                path="/register"
                element={
                    <GuestOnlyRoute>
                        <AuthLayout
                            title="Create an account"
                            description="Enter your details below to create your account"
                        >
                            <Register />
                        </AuthLayout>
                    </GuestOnlyRoute>
                }
            />
            <Route
                path="/forgot-password"
                element={
                    <GuestOnlyRoute>
                        <AuthLayout
                            title="Forgot password"
                            description="Enter your email to receive a password reset link"
                        >
                            <ForgotPassword />
                        </AuthLayout>
                    </GuestOnlyRoute>
                }
            />
            <Route
                path="/reset-password/:token"
                element={
                    <GuestOnlyRoute>
                        <AuthLayout
                            title="Reset password"
                            description="Please enter your new password below"
                        >
                            <ResetPassword />
                        </AuthLayout>
                    </GuestOnlyRoute>
                }
            />
            <Route
                path="/verify-email"
                element={
                    <ProtectedRoute>
                        <AuthLayout
                            title="Email verification"
                            description="Please verify your email address by clicking on the link we just emailed to you."
                        >
                            <VerifyEmail />
                        </AuthLayout>
                    </ProtectedRoute>
                }
            />

            <Route
                path="/"
                element={
                    <ProtectedRoute>
                        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/' }]}>
                            <Dashboard />
                        </AppLayout>
                    </ProtectedRoute>
                }
            />
            <Route
                path="/settings/profile"
                element={
                    <ProtectedRoute>
                        <AppLayout
                            breadcrumbs={[{ title: 'Profile settings', href: '/settings/profile' }]}
                        >
                            <SettingsLayout>
                                <Profile />
                            </SettingsLayout>
                        </AppLayout>
                    </ProtectedRoute>
                }
            />
            <Route
                path="/settings/appearance"
                element={
                    <ProtectedRoute>
                        <AppLayout
                            breadcrumbs={[
                                { title: 'Appearance settings', href: '/settings/appearance' },
                            ]}
                        >
                            <SettingsLayout>
                                <Appearance />
                            </SettingsLayout>
                        </AppLayout>
                    </ProtectedRoute>
                }
            />

            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}
