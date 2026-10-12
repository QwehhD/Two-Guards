import { Fragment, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import AppLogo from '@/components/app-logo';
import { GateScene } from '@/components/gate-scene';
import { RecentActivityList } from '@/components/landing/recent-activity-list';
import { Marker } from '@/components/marker';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { echo } from '@/services/echo';
import {
    fetchPortalStatus,
    fetchRecentActivity,
} from '@/services/publicPortalService';
import { useAuthStore } from '@/store/auth';
import type { DeviceMode, PublicAccessLog, PublicDeviceStatus } from '@/types';

const APP_NAME = import.meta.env.VITE_APP_NAME ?? 'Two-Guards';

const MODE_LABELS: Record<DeviceMode, string> = {
    auto: 'Otomatis',
    manual: 'Manual',
};

const STEPS = [
    {
        title: 'Tempel kartu',
        body: 'Kartu RFID ditempel di reader. ESP32 mengirim UID-nya ke server lewat MQTT.',
    },
    {
        title: 'Server memutuskan',
        body: 'Mode otomatis: kartu aktif langsung diizinkan. Mode manual: petugas menyetujui atau menolak dari dashboard.',
    },
    {
        title: 'Palang bergerak',
        body: 'Palang terbuka beberapa detik lalu menutup sendiri. Gerbang di halaman ini ikut bergerak saat itu juga.',
    },
];

/** The giant scrolling wordmark. Rendered twice so a -50% shift loops seamlessly. */
function Marquee() {
    const run = (
        <div className="flex shrink-0 items-center gap-[0.3em] pr-[0.3em]">
            {[APP_NAME, 'Portal Parkir'].map((word) => (
                <Fragment key={word}>
                    <span>{word}</span>
                    <span className="bg-primary inline-block size-[0.24em] shrink-0" />
                </Fragment>
            ))}
        </div>
    );

    return (
        <div aria-hidden className="overflow-hidden border-b">
            <div className="motion-safe:animate-marquee flex w-max py-2 font-mono text-[clamp(3.5rem,12vw,9.5rem)] leading-none font-bold tracking-tight uppercase">
                {run}
                {run}
            </div>
        </div>
    );
}

/** The corner brackets around the dark status tile, as in the reference. */
function Corners() {
    const corner = 'absolute size-3 border-current';

    return (
        <>
            <span className={cn(corner, 'top-3 left-3 border-t-2 border-l-2')} />
            <span className={cn(corner, 'top-3 right-3 border-t-2 border-r-2')} />
            <span className={cn(corner, 'bottom-3 left-3 border-b-2 border-l-2')} />
            <span className={cn(corner, 'right-3 bottom-3 border-r-2 border-b-2')} />
        </>
    );
}

function SectionLabel({ children, aside }: { children: string; aside?: string }) {
    return (
        <div className="label-mono flex items-center justify-between gap-4 border-b px-6 py-3">
            <span className="flex items-center gap-2">
                <Marker />
                {children}
            </span>
            {aside && <span className="text-muted-foreground">{aside}</span>}
        </div>
    );
}

/**
 * Public landing page (Tahap 7) — the only page in the app reachable
 * without logging in. Rendered directly at "/" in app.tsx, with no
 * ProtectedRoute/GuestOnlyRoute wrapper, so it stays visible to signed-out
 * visitors and signed-in staff alike.
 *
 * The gate drawing in the middle follows the first device's real
 * portal_status, and ripples at its card reader on every new scan.
 */
export default function Landing() {
    const user = useAuthStore((state) => state.user);

    const [devices, setDevices] = useState<PublicDeviceStatus[]>([]);
    const [activity, setActivity] = useState<PublicAccessLog[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    // Bumped on every live activity update: replays the gate's scan ripple.
    const [scanKey, setScanKey] = useState(0);

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
                setScanKey((key) => key + 1);
            },
        );

        return () => {
            echo.leave('landing');
        };
    }, []);

    const device = devices[0] ?? null;
    const isOpen = device?.portal_status === 'open';
    const isOnline = device?.status === 'online';

    let gateCaption = isOpen ? 'Palang terbuka' : 'Palang tertutup';
    if (loading) gateCaption = 'Memuat status…';
    else if (!device) gateCaption = 'Belum ada perangkat terdaftar';
    else if (!isOnline) gateCaption = 'Perangkat offline';

    return (
        <main className="bg-grid text-foreground min-h-svh">
            <h1 className="sr-only">Status portal parkir {APP_NAME}</h1>

            <header className="bg-background/85 sticky top-0 z-40 border-b backdrop-blur">
                <div className="flex h-16 items-stretch">
                    <Link to="/" className="flex items-center gap-1 border-r px-6">
                        <AppLogo />
                    </Link>
                    <nav className="label-mono hidden flex-1 items-center justify-center gap-10 md:flex">
                        <a href="#status" className="hover:text-primary transition-colors">
                            Status
                        </a>
                        <a href="#aktivitas" className="hover:text-primary transition-colors">
                            Aktivitas
                        </a>
                        <a href="#cara-kerja" className="hover:text-primary transition-colors">
                            Cara kerja
                        </a>
                    </nav>
                    <div className="ml-auto flex items-center border-l px-6">
                        <Button asChild size="sm">
                            <Link to={user ? '/dashboard' : '/login'}>{user ? 'Dashboard' : 'Login'}</Link>
                        </Button>
                    </div>
                </div>
            </header>

            <Marquee />

            <div className="label-mono flex flex-wrap items-center gap-x-8 gap-y-2 border-b px-6 py-3">
                <span className="flex items-center gap-2">
                    <Marker />
                    Gerbang RFID
                </span>
                <span className="flex items-center gap-2">
                    <Marker />
                    Status realtime
                </span>
            </div>

            {error && (
                <div className="border-b p-6">
                    <Alert variant="destructive">
                        <AlertTitle>Gagal memuat data</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                </div>
            )}

            <section id="status" className="grid scroll-mt-16 border-b lg:grid-cols-[1fr_1.6fr_1fr]">
                <div className="flex flex-col justify-between gap-8 border-b p-6 lg:border-r lg:border-b-0">
                    <p className="max-w-sm font-mono text-base leading-relaxed">
                        Gerbang parkir yang dibuka oleh kartu RFID. Palang di halaman ini mengikuti portal
                        sungguhan, detik itu juga.
                    </p>
                    <Button asChild className="self-start">
                        <a href="#aktivitas">Lihat aktivitas</a>
                    </Button>
                </div>

                <div className="flex flex-col items-center justify-end gap-4 border-b px-6 pt-10 pb-6 lg:border-r lg:border-b-0">
                    <GateScene
                        open={isOpen}
                        online={loading || isOnline}
                        scanKey={scanKey}
                        className="max-w-lg"
                    />
                    <p className="label-mono text-muted-foreground flex items-center gap-2" aria-live="polite">
                        <Marker className={cn(!(isOpen && isOnline) && 'bg-muted-foreground')} />
                        {gateCaption}
                    </p>
                </div>

                <div className="p-6">
                    <div className="bg-foreground text-background relative flex aspect-square flex-col justify-between p-8">
                        <Corners />
                        <span className="label-mono opacity-60">{device?.name ?? 'Portal'}</span>
                        <span
                            key={device?.portal_status}
                            className="motion-safe:animate-in motion-safe:fade-in font-mono text-[clamp(2rem,4vw,3rem)] leading-none font-bold uppercase duration-500"
                        >
                            {loading ? '—' : isOpen ? 'Terbuka' : 'Tertutup'}
                        </span>
                        <dl className="label-mono grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                            <dt className="opacity-60">Perangkat</dt>
                            <dd className="flex items-center gap-2">
                                <span className={cn('size-2 rounded-full', isOnline ? 'bg-primary' : 'bg-background/40')} />
                                {loading ? '—' : isOnline ? 'Online' : 'Offline'}
                            </dd>
                            <dt className="opacity-60">Mode</dt>
                            <dd>{device ? MODE_LABELS[device.mode] : '—'}</dd>
                        </dl>
                    </div>
                </div>
            </section>

            {devices.length > 1 && (
                <section className="border-b">
                    <SectionLabel>Semua portal</SectionLabel>
                    <ul className="grid sm:grid-cols-2 lg:grid-cols-4">
                        {devices.map((item) => (
                            <li key={item.name} className="bg-card flex flex-col gap-2 border-b p-6 sm:border-r">
                                <span className="font-mono text-sm font-bold uppercase">{item.name}</span>
                                <span className="label-mono text-muted-foreground">
                                    {item.status === 'online' ? 'Online' : 'Offline'} ·{' '}
                                    {item.portal_status === 'open' ? 'Terbuka' : 'Tertutup'} · {MODE_LABELS[item.mode]}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section id="aktivitas" className="scroll-mt-16 border-b">
                <SectionLabel aside="Diperbarui realtime">Aktivitas terakhir</SectionLabel>
                {!loading && !error && (
                    <div key={scanKey} className="motion-safe:animate-in motion-safe:fade-in duration-500">
                        <RecentActivityList activity={activity} />
                    </div>
                )}
            </section>

            <section id="cara-kerja" className="scroll-mt-16 border-b">
                <SectionLabel>Cara kerja</SectionLabel>
                <ol className="grid md:grid-cols-3">
                    {STEPS.map((step, index) => (
                        <li key={step.title} className="bg-card flex flex-col gap-4 border-b p-6 md:border-r md:border-b-0">
                            <span className="text-primary font-mono text-4xl font-bold">
                                {String(index + 1).padStart(2, '0')}
                            </span>
                            <span className="font-mono text-sm font-bold uppercase">{step.title}</span>
                            <p className="text-muted-foreground text-sm">{step.body}</p>
                        </li>
                    ))}
                </ol>
            </section>

            <footer className="label-mono text-muted-foreground flex flex-wrap items-center justify-between gap-4 px-6 py-6">
                <span>
                    © {new Date().getFullYear()} {APP_NAME}
                </span>
                <span>Status diperbarui otomatis secara real-time.</span>
            </footer>
        </main>
    );
}
