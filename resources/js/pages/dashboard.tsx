import { AlertCircle } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { fetchDevices, updateDeviceMode } from '@/services/deviceService';
import { echo } from '@/services/echo';
import { useAuthStore } from '@/store/auth';
import type { Device, DeviceMode } from '@/types';

const MODE_LABELS: Record<DeviceMode, string> = {
    auto: 'Otomatis',
    manual: 'Manual',
};

const MODE_HINTS: Record<DeviceMode, string> = {
    auto: 'Kartu aktif langsung membuka palang; kartu lain ditolak.',
    manual: 'Setiap scan menunggu Approve/Reject di halaman Persetujuan, paling lama 30 detik.',
};

function otherMode(mode: DeviceMode): DeviceMode {
    return mode === 'auto' ? 'manual' : 'auto';
}

function formatDateTime(value: string | null): string {
    if (!value) return '-';

    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'medium',
    });
}

/**
 * Every portal at a glance: online or not, gate position, and mode. Admins
 * switch a device between auto and manual here (PATCH /api/devices/{id} is
 * admin-only too).
 */
export default function Dashboard() {
    const user = useAuthStore((state) => state.user);
    const canSwitchMode = user?.role === 'admin';

    const [devices, setDevices] = useState<Device[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [switchingIds, setSwitchingIds] = useState<Set<number>>(new Set());

    // `silent` is for refetches the live feed triggers: no skeleton, and a
    // failed refresh keeps the cards already on screen.
    const loadDevices = useCallback(async ({ silent = false }: { silent?: boolean } = {}) => {
        if (!silent) {
            setLoading(true);
            setError(null);
        }

        try {
            setDevices(await fetchDevices());
        } catch {
            if (!silent) setError('Gagal memuat daftar perangkat.');
        } finally {
            if (!silent) setLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadDevices();
    }, [loadDevices]);

    useEffect(() => {
        // The landing page's public channel: PortalStatusUpdated fires on every
        // status message from a device (heartbeat, LWT, gate opening or
        // closing) and on every mode switch. Its payload carries no device
        // ids, so this refetches rather than reading it.
        const channel = echo.channel('landing');
        channel.listen('.PortalStatusUpdated', () => void loadDevices({ silent: true }));

        return () => {
            echo.leave('landing');
        };
    }, [loadDevices]);

    const handleSwitchMode = async (device: Device) => {
        setSwitchingIds((prev) => new Set(prev).add(device.id));

        try {
            const updated = await updateDeviceMode(device.id, otherMode(device.mode));
            setDevices((prev) => prev.map((item) => (item.id === updated.id ? updated : item)));
            toast.success(`${updated.name} sekarang mode ${MODE_LABELS[updated.mode]}.`);
        } catch {
            toast.error('Gagal mengganti mode. Silakan coba lagi.');
        } finally {
            setSwitchingIds((prev) => {
                const next = new Set(prev);
                next.delete(device.id);

                return next;
            });
        }
    };

    return (
        <div className="flex flex-1 flex-col gap-4 p-4">
            <div>
                <h1 className="text-xl font-semibold">Dashboard</h1>
                <p className="text-muted-foreground text-sm">
                    Kondisi setiap portal, diperbarui otomatis setiap kali perangkat mengirim status.
                </p>
            </div>

            {error && (
                <Alert variant="destructive">
                    <AlertCircle />
                    <AlertTitle>Terjadi kesalahan</AlertTitle>
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {loading && (
                    <Card>
                        <CardContent className="flex flex-col gap-3">
                            <Skeleton className="h-5 w-1/2" />
                            <Skeleton className="h-4 w-3/4" />
                            <Skeleton className="h-4 w-2/3" />
                        </CardContent>
                    </Card>
                )}

                {!loading && !error && devices.length === 0 && (
                    <p className="text-muted-foreground text-sm">Belum ada perangkat.</p>
                )}

                {!loading &&
                    devices.map((device) => {
                        const switching = switchingIds.has(device.id);

                        return (
                            <Card key={device.id}>
                                <CardHeader>
                                    <CardTitle>{device.name}</CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3 text-sm">
                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant={device.status === 'online' ? 'default' : 'destructive'}>
                                            {device.status === 'online' ? 'Online' : 'Offline'}
                                        </Badge>
                                        <Badge variant="outline">
                                            {device.portal_status === 'open' ? 'Palang terbuka' : 'Palang tertutup'}
                                        </Badge>
                                    </div>

                                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                                        <dt className="text-muted-foreground">Mode</dt>
                                        <dd className="font-medium">{MODE_LABELS[device.mode]}</dd>
                                        <dt className="text-muted-foreground">Terakhir terlihat</dt>
                                        <dd>{formatDateTime(device.last_seen_at)}</dd>
                                    </dl>

                                    <p className="text-muted-foreground text-xs">{MODE_HINTS[device.mode]}</p>

                                    {canSwitchMode && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="self-start"
                                            disabled={switching}
                                            onClick={() => void handleSwitchMode(device)}
                                        >
                                            {switching && <Spinner />}
                                            Ganti ke {MODE_LABELS[otherMode(device.mode)]}
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
            </div>
        </div>
    );
}
