import { useEffect, useState } from 'react';
import { DeviceStatusCard } from '@/components/landing/device-status-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Skeleton } from '@/components/ui/skeleton';
import { fetchPortalStatus } from '@/services/publicPortalService';
import type { PublicDeviceStatus } from '@/types';

/**
 * Public landing page (Tahap 7) — the only page in the app reachable
 * without logging in. Rendered directly at "/" in app.tsx, with no
 * ProtectedRoute/GuestOnlyRoute wrapper, so it stays visible to signed-out
 * visitors and signed-in staff alike.
 *
 * Fetches the device snapshot once on mount for now; recurring polling
 * (clearly marked as a Tahap-8-WebSocket stand-in) and the recent-activity
 * section land in follow-up commits.
 */
export default function Landing() {
    const [devices, setDevices] = useState<PublicDeviceStatus[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        fetchPortalStatus()
            .then((data) => {
                if (!cancelled) setDevices(data);
            })
            .catch(() => {
                if (!cancelled) {
                    setError('Gagal memuat status portal. Coba muat ulang halaman.');
                }
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, []);

    return (
        <main className="bg-background text-foreground min-h-svh">
            <div className="mx-auto flex max-w-3xl flex-col gap-8 px-6 py-16">
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
            </div>
        </main>
    );
}
