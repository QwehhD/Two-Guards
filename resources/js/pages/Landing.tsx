import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import AppLogo from '@/components/app-logo';
import { DeviceStatusCard } from '@/components/landing/device-status-card';
import { RecentActivityList } from '@/components/landing/recent-activity-list';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { echo } from '@/services/echo';
import {
    fetchPortalStatus,
    fetchRecentActivity,
} from '@/services/publicPortalService';
import type { PublicAccessLog, PublicDeviceStatus } from '@/types';

/**
 * Public landing page (Tahap 7) — the only page in the app reachable
 * without logging in. Rendered directly at "/" in app.tsx, with no
 * ProtectedRoute/GuestOnlyRoute wrapper, so it stays visible to signed-out
 * visitors and signed-in staff alike.
 */
export default function Landing() {
    const [devices, setDevices] = useState<PublicDeviceStatus[]>([]);
    const [activity, setActivity] = useState<PublicAccessLog[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    // One-off snapshot for the first paint, before the "landing" channel
    // subscription below has connected — that channel is what keeps this
    // page live afterwards, not a repeated fetch.
    useEffect(() => {
        let cancelled = false;

        Promise.all([fetchPortalStatus(), fetchRecentActivity()])
            .then(([devicesData, activityData]) => {
                if (cancelled) return;
                setDevices(devicesData);
                setActivity(activityData);
                setError(null);
            })
            .catch(() => {
                if (!cancelled) {
                    setError('Gagal memuat data portal. Coba muat ulang halaman.');
                }
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, []);

    useEffect(() => {
        // Public channel — no `.private()`/auth needed, matching this
        // page being reachable by signed-out visitors too.
        const channel = echo.channel('landing');

        // Leading "." on both event names — see the same note on the
        // Approvals page's private-channel subscription: broadcastAs()
        // on the backend events means no "App.Events." namespace prefix
        // is sent, so Echo must be told not to expect one either.
        channel.listen(
            '.PortalStatusUpdated',
            ({ devices: nextDevices }: { devices: PublicDeviceStatus[] }) => {
                setDevices(nextDevices);
            },
        );

        channel.listen(
            '.RecentActivityUpdated',
            ({ recent_activity: nextActivity }: { recent_activity: PublicAccessLog[] }) => {
                setActivity(nextActivity);
            },
        );

        return () => {
            echo.leave('landing');
        };
    }, []);

    return (
        <main className="bg-background text-foreground min-h-svh">
            <div className="mx-auto flex max-w-3xl flex-col gap-10 px-6 py-16">
                <header className="flex items-center justify-between gap-4">
                    <div className="flex items-center">
                        <AppLogo />
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link to="/login">Login</Link>
                    </Button>
                </header>

                <div className="flex flex-col items-center gap-4 text-center">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Status Portal Parkir
                    </h1>
                    <p className="text-muted-foreground max-w-prose">
                        Pantau status gerbang portal secara umum dan real-time.
                    </p>
                </div>

                {error && (
                    <Alert variant="destructive">
                        <AlertTitle>Gagal memuat data</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                {loading && !error && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Skeleton className="h-32 rounded-xl" />
                        <Skeleton className="h-32 rounded-xl" />
                    </div>
                )}

                {!loading && !error && devices.length === 0 && (
                    <p className="text-muted-foreground text-center text-sm">
                        Belum ada perangkat portal yang terdaftar.
                    </p>
                )}

                {!loading && devices.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {devices.map((device) => (
                            <DeviceStatusCard key={device.name} device={device} />
                        ))}
                    </div>
                )}

                {!loading && !error && (
                    <div className="flex flex-col gap-3">
                        <h2 className="text-lg font-semibold tracking-tight">
                            Aktivitas Terakhir
                        </h2>
                        <RecentActivityList activity={activity} />
                    </div>
                )}

                {!loading && !error && (
                    <p className="text-muted-foreground text-center text-xs">
                        Status diperbarui otomatis secara real-time.
                    </p>
                )}
            </div>
        </main>
    );
}
