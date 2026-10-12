import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import AccessLogs from '@/pages/access-logs';
import Approvals from '@/pages/approvals';
import Login from '@/pages/auth/login';
import Dashboard from '@/pages/dashboard';
import Landing from '@/pages/Landing';
import RfidCards from '@/pages/rfid-cards';
import Appearance from '@/pages/settings/appearance';
import Profile from '@/pages/settings/profile';
import Users from '@/pages/users';
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

/**
 * Only for pages inside ProtectedRoute, where the user is already loaded.
 * The API enforces the same rule; this just keeps karyawan off a page whose
 * every request would be refused.
 */
function AdminRoute({ children }: { children: ReactNode }) {
    const user = useAuthStore((state) => state.user);

    if (user?.role !== 'admin') {
        return <Navigate to="/dashboard" replace />;
    }

    return <>{children}</>;
}

function GuestOnlyRoute({ children }: { children: ReactNode }) {
    const status = useAuthStore((state) => state.status);

    if (status === 'idle' || status === 'loading') {
        return null;
    }

    if (status === 'authenticated') {
        return <Navigate to="/dashboard" replace />;
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
            {/*
                Public landing page (Tahap 7) — the only route in the app
                that is intentionally left unwrapped by ProtectedRoute or
                GuestOnlyRoute, so it renders the same for guests and
                signed-in staff alike.
            */}
            <Route path="/" element={<Landing />} />
            <Route
                path="/dashboard"
                element={
                    <ProtectedRoute>
                        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
                            <Dashboard />
                        </AppLayout>
                    </ProtectedRoute>
                }
            />
            <Route
                path="/access-logs"
                element={
                    <ProtectedRoute>
                        <AppLayout breadcrumbs={[{ title: 'Access Logs', href: '/access-logs' }]}>
                            <AccessLogs />
                        </AppLayout>
                    </ProtectedRoute>
                }
            />
            <Route
                path="/approvals"
                element={
                    <ProtectedRoute>
                        <AppLayout breadcrumbs={[{ title: 'Persetujuan Manual', href: '/approvals' }]}>
                            <Approvals />
                        </AppLayout>
                    </ProtectedRoute>
                }
            />
            <Route
                path="/users"
                element={
                    <ProtectedRoute>
                        <AdminRoute>
                            <AppLayout breadcrumbs={[{ title: 'Manajemen Akun', href: '/users' }]}>
                                <Users />
                            </AppLayout>
                        </AdminRoute>
                    </ProtectedRoute>
                }
            />
            <Route
                path="/rfid-cards"
                element={
                    <ProtectedRoute>
                        <AdminRoute>
                            <AppLayout breadcrumbs={[{ title: 'Kartu RFID', href: '/rfid-cards' }]}>
                                <RfidCards />
                            </AppLayout>
                        </AdminRoute>
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
