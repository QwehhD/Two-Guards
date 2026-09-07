import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { simulateScan } from '@/services/accessLogService';
import { fetchDevices } from '@/services/deviceService';
import type { Device } from '@/types';

/**
 * ============================================================
 *  DEVELOPMENT-ONLY COMPONENT — NOT PART OF THE PRODUCTION UI
 * ============================================================
 * Lets a developer trigger a simulated card scan (via the dev-only
 * POST /api/access-logs/simulate-scan endpoint) without waiting for real
 * ESP32/MQTT hardware to exist (Tahap 9). The parent page is expected to
 * only render this when import.meta.env.DEV is true; remove it (and its
 * usage) once real hardware is wired up.
 */
export function SimulateScanButton() {
    const [devices, setDevices] = useState<Device[]>([]);
    const [deviceId, setDeviceId] = useState('');
    const [uid, setUid] = useState('');
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        fetchDevices()
            .then(setDevices)
            .catch(() => toast.error('Gagal memuat daftar device untuk simulasi.'));
    }, []);

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();

        if (!deviceId) {
            toast.error('Pilih device dulu.');
            return;
        }

        setSubmitting(true);

        try {
            const log = await simulateScan({
                device_id: Number(deviceId),
                uid: uid.trim() || undefined,
            });

            toast.success(
                `Scan disimulasikan (${log.status}): ${log.owner_name} di ${log.device?.name ?? 'device'}`,
            );
            setUid('');
        } catch {
            toast.error('Gagal mensimulasikan scan. Cek konsol untuk detail.');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Card className="border-dashed">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                    <span className="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                        DEV ONLY
                    </span>
                    Simulasikan Scan Kartu
                </CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => void handleSubmit(e)}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                >
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="sim-device">Device</Label>
                        <Select value={deviceId} onValueChange={setDeviceId}>
                            <SelectTrigger id="sim-device" className="w-full">
                                <SelectValue placeholder="Pilih device" />
                            </SelectTrigger>
                            <SelectContent>
                                {devices.map((device) => (
                                    <SelectItem key={device.id} value={String(device.id)}>
                                        {device.name} ({device.mode})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="sim-uid">UID kartu (kosongkan = kartu tak dikenal)</Label>
                        <Input
                            id="sim-uid"
                            value={uid}
                            onChange={(e) => setUid(e.target.value)}
                            placeholder="Contoh: A1B2C3D4"
                        />
                    </div>

                    <Button type="submit" disabled={submitting}>
                        {submitting ? 'Mengirim...' : 'Simulasikan Scan'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
