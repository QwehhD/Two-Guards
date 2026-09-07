import axios from 'axios';
import { Check, Clock, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { SimulateScanButton } from '@/components/simulate-scan-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    approveAccessLog,
    fetchPendingAccessLogs,
    rejectAccessLog,
} from '@/services/accessLogService';
import type { AccessLog } from '@/types';

// TEMPORARY (Tahap 6): there's no WebSocket/real-time feed yet, so this
// page polls the pending-scans list on an interval. Replace this with a
// live subscription once Tahap 8 wires up WebSocket, and remove this
// comment along with the polling effect below.
const POLL_INTERVAL_MS = 4000;

// Mirrors AccessLog::PENDING_TIMEOUT_SECONDS on the backend. This is only
// used to render a live countdown on the client — the backend independently
// enforces (and self-heals) the real timeout, so a clock drift or a missed
// tick here never lets a stale scan get approved late.
const PENDING_TIMEOUT_SECONDS = 30;

function sortByScannedAtDesc(logs: AccessLog[]): AccessLog[] {
    return [...logs].sort(
        (a, b) => new Date(b.scanned_at ?? 0).getTime() - new Date(a.scanned_at ?? 0).getTime(),
    );
}

function secondsRemaining(scannedAt: string | null): number {
    if (!scannedAt) return 0;

    const deadline = new Date(scannedAt).getTime() + PENDING_TIMEOUT_SECONDS * 1000;

    return Math.max(0, Math.round((deadline - Date.now()) / 1000));
}

function formatDateTime(value: string | null): string {
    if (!value) return '-';

    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'medium',
    });
}

type PendingCardProps = {
    log: AccessLog;
    processing: boolean;
    onApprove: () => void;
    onReject: () => void;
};

function PendingCard({ log, processing, onApprove, onReject }: PendingCardProps) {
    const [remaining, setRemaining] = useState(() => secondsRemaining(log.scanned_at));

    useEffect(() => {
        const tick = setInterval(() => setRemaining(secondsRemaining(log.scanned_at)), 1000);
        return () => clearInterval(tick);
    }, [log.scanned_at]);

    const expiring = remaining <= 10;

    return (
        <Card>
            <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-2">
                        <span className="font-medium">
                            {log.is_known_card ? (
                                log.owner_name
                            ) : (
                                <span className="text-muted-foreground italic">
                                    {log.owner_name}
                                </span>
                            )}
                        </span>
                        <Badge variant={expiring ? 'destructive' : 'secondary'} className="gap-1">
                            <Clock className="size-3" />
                            {remaining > 0 ? `${remaining}s` : 'Kedaluwarsa'}
                        </Badge>
                    </div>
                    <p className="text-muted-foreground text-sm">
                        {log.device?.name ?? 'Device tidak diketahui'} · {formatDateTime(log.scanned_at)}
                    </p>
                    <p className="text-muted-foreground font-mono text-xs">{log.scanned_uid}</p>
                </div>

                <div className="flex shrink-0 gap-2">
                    <Button
                        size="sm"
                        disabled={processing}
                        onClick={onApprove}
                        className="bg-green-600 text-white hover:bg-green-700"
                    >
                        <Check /> Approve
                    </Button>
                    <Button size="sm" variant="destructive" disabled={processing} onClick={onReject}>
                        <X /> Reject
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

export default function Approvals() {
    const [logs, setLogs] = useState<AccessLog[]>([]);
    const [loading, setLoading] = useState(true);
    const [processingIds, setProcessingIds] = useState<Set<number>>(new Set());

    // Tracks which log IDs we've already shown to the user, so the
    // "new scan" toast only fires for scans that showed up since the last
    // poll — not for every item on the very first load.
    const knownIdsRef = useRef<Set<number> | null>(null);

    const loadPending = useCallback(async () => {
        try {
            const response = await fetchPendingAccessLogs();
            const nextLogs = sortByScannedAtDesc(response.data);

            if (knownIdsRef.current) {
                const newOnes = nextLogs.filter((log) => !knownIdsRef.current!.has(log.id));
                for (const log of newOnes) {
                    toast.info(`Scan baru menunggu persetujuan: ${log.owner_name}`, {
                        description: log.device?.name ?? undefined,
                    });
                }
            }

            knownIdsRef.current = new Set(nextLogs.map((log) => log.id));
            setLogs(nextLogs);
        } catch {
            // Silent on polling failures — the list simply won't refresh
            // until the next tick. Surfacing an error toast every few
            // seconds for a background poll would be worse UX than a
            // briefly stale list.
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadPending();

        const interval = setInterval(() => void loadPending(), POLL_INTERVAL_MS);
        return () => clearInterval(interval);
    }, [loadPending]);

    const handleDecision = async (log: AccessLog, decision: 'approve' | 'reject') => {
        setProcessingIds((prev) => new Set(prev).add(log.id));
        setLogs((prev) => prev.filter((item) => item.id !== log.id));

        try {
            if (decision === 'approve') {
                await approveAccessLog(log.id);
                toast.success(`Disetujui: ${log.owner_name}`);
            } else {
                await rejectAccessLog(log.id);
                toast.success(`Ditolak: ${log.owner_name}`);
            }
        } catch (err) {
            const status = axios.isAxiosError(err) ? err.response?.status : undefined;
            const message = axios.isAxiosError(err)
                ? (err.response?.data as { message?: string } | undefined)?.message
                : undefined;

            if (status === 422) {
                // The backend refused because this scan's state already
                // changed server-side (already processed by someone else,
                // or just self-healed to expired) — the optimistic removal
                // was correct, just for a different reason. Don't restore it.
                toast.error(message ?? `Scan ${log.owner_name} tidak bisa diproses.`);
            } else {
                // A genuine failure (network/5xx) — we can't tell whether
                // the server even saw the request, so restore the item and
                // let the user retry.
                setLogs((prev) => sortByScannedAtDesc([...prev, log]));
                toast.error(`Gagal memproses scan ${log.owner_name}. Coba lagi.`);
            }
        } finally {
            setProcessingIds((prev) => {
                const next = new Set(prev);
                next.delete(log.id);
                return next;
            });
        }
    };

    return (
        <div className="flex flex-1 flex-col gap-4 p-4">
            <div>
                <h1 className="text-xl font-semibold">Persetujuan Manual</h1>
                <p className="text-muted-foreground text-sm">
                    Scan kartu yang menunggu keputusan Approve/Reject pada device bermode manual.
                </p>
            </div>

            {/* DEV ONLY: lets you test this flow before real ESP32/MQTT hardware exists (Tahap 9). */}
            {import.meta.env.DEV && <SimulateScanButton />}

            {loading && (
                <div className="flex flex-col gap-3">
                    {Array.from({ length: 2 }).map((_, i) => (
                        <Skeleton key={i} className="h-24 w-full" />
                    ))}
                </div>
            )}

            {!loading && logs.length === 0 && (
                <Card>
                    <CardContent className="text-muted-foreground py-10 text-center">
                        Tidak ada scan yang menunggu persetujuan saat ini.
                    </CardContent>
                </Card>
            )}

            <div className="flex flex-col gap-3">
                {logs.map((log) => (
                    <PendingCard
                        key={log.id}
                        log={log}
                        processing={processingIds.has(log.id)}
                        onApprove={() => void handleDecision(log, 'approve')}
                        onReject={() => void handleDecision(log, 'reject')}
                    />
                ))}
            </div>
        </div>
    );
}
