import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import AppLogo from '@/components/app-logo';
import { DeviceStatusCard } from '@/components/landing/device-status-card';
import { RecentActivityList } from '@/components/landing/recent-activity-list';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    fetchPortalStatus,
    fetchRecentActivity,
} from '@/services/publicPortalService';
import type { PublicAccessLog, PublicDeviceStatus } from '@/types';

// TEMPORARY (Tahap 7): plain polling stands in for real-time updates
// until Tahap 8 replaces it with WebSockets. Only this interval and the
// load() call site below are expected to change then — state shape and
// rendering stay the same.
const POLL_INTERVAL_MS = 5000;

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

    useEffect(() => {
        let cancelled = false;

        // `isInitial` distinguishes the first load (shows the skeleton,
        // surfaces errors) from a background poll (updates silently on
        // success; on failure, keeps the last known-good state on screen
        // rather than blanking a working public page over one dropped
        // request — it'll just try again in POLL_INTERVAL_MS).
        const load = (isInitial: boolean) => {
            if (isInitial) setLoading(true);

            return Promise.all([fetchPortalStatus(), fetchRecentActivity()])
                .then(([devicesData, activityData]) => {
                    if (cancelled) return;
                    setDevices(devicesData);
                    setActivity(activityData);
                    setError(null);
                })
                .catch(() => {
                    if (!cancelled && isInitial) {
                        setError('Gagal memuat data portal. Coba muat ulang halaman.');
                    }
                })
                .finally(() => {
                    if (!cancelled && isInitial) setLoading(false);
                });
        };

        load(true);
        const intervalId = setInterval(() => load(false), POLL_INTERVAL_MS);

        return () => {
            cancelled = true;
            clearInterval(intervalId);
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
                        Status diperbarui otomatis setiap beberapa detik.
                    </p>
                )}
            </div>
        </main>
    );
}
