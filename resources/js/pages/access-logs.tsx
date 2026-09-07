import { AlertCircle } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { RegisterRfidCardDialog } from '@/components/rfid-cards/register-rfid-card-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { fetchAccessLogs } from '@/services/accessLogService';
import { useAuthStore } from '@/store/auth';
import type {
    AccessLog,
    AccessLogFilters,
    AccessLogMode,
    AccessLogStatus,
    PaginationMeta,
} from '@/types';

const STATUS_OPTIONS: { value: AccessLogStatus; label: string }[] = [
    { value: 'approved', label: 'Disetujui' },
    { value: 'denied', label: 'Ditolak' },
    { value: 'pending', label: 'Menunggu' },
    { value: 'expired', label: 'Kedaluwarsa' },
];

const MODE_OPTIONS: { value: AccessLogMode; label: string }[] = [
    { value: 'auto', label: 'Otomatis' },
    { value: 'manual', label: 'Manual' },
];

const STATUS_LABEL: Record<AccessLogStatus, string> = {
    approved: 'Disetujui',
    denied: 'Ditolak',
    pending: 'Menunggu',
    expired: 'Kedaluwarsa',
};

const STATUS_BADGE_VARIANT: Record<
    AccessLogStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    approved: 'default',
    denied: 'destructive',
    pending: 'secondary',
    expired: 'outline',
};

const MODE_LABEL: Record<AccessLogMode, string> = {
    auto: 'Otomatis',
    manual: 'Manual',
};

const SEARCH_DEBOUNCE_MS = 400;

function formatDateTime(value: string | null): string {
    if (!value) return '-';

    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export default function AccessLogs() {
    const [searchParams, setSearchParams] = useSearchParams();
    const user = useAuthStore((state) => state.user);

    // Registering a card is admin-only (mirrors RfidCardPolicy on the
    // backend); karyawan can view logs but never see the register action.
    const canRegisterCards = user?.role === 'admin';

    // Logs live in their own array (not the raw paginated response), so
    // when Tahap 8 wires up the MQTT/WebSocket feed, a new scan can be
    // prepended with setLogs((prev) => [newLog, ...prev]) without having to
    // touch or recompute the pagination meta below.
    const [logs, setLogs] = useState<AccessLog[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    // The log currently being registered from an unknown row, if any; also
    // doubles as the register dialog's open/closed state.
    const [registeringLog, setRegisteringLog] = useState<AccessLog | null>(null);

    const status = searchParams.get('status') ?? 'all';
    const mode = searchParams.get('mode') ?? 'all';
    const dateFrom = searchParams.get('date_from') ?? '';
    const dateTo = searchParams.get('date_to') ?? '';

    // The search box gets its own local state so typing doesn't trigger a
    // fetch on every keystroke. It's synced into the URL (which is what
    // actually drives the fetch) only after the user pauses typing.
    const [searchInput, setSearchInput] = useState(searchParams.get('search') ?? '');
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const updateParams = useCallback(
        (patch: Record<string, string | null>, resetPage = true) => {
            setSearchParams((prev) => {
                const next = new URLSearchParams(prev);
                for (const [key, value] of Object.entries(patch)) {
                    if (value === null || value === '') {
                        next.delete(key);
                    } else {
                        next.set(key, value);
                    }
                }
                if (resetPage) {
                    next.delete('page');
                }
                return next;
            });
        },
        [setSearchParams],
    );

    useEffect(() => {
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => {
            const current = searchParams.get('search') ?? '';
            if (searchInput !== current) {
                updateParams({ search: searchInput || null });
            }
        }, SEARCH_DEBOUNCE_MS);

        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }
        };
        // Only re-run when the user types; updateParams/searchParams are
        // stable enough here and including them would refire this timer
        // whenever any other filter changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchInput]);

    const buildFilters = useCallback((): AccessLogFilters => {
        const filters: AccessLogFilters = {};

        const statusParam = searchParams.get('status');
        if (statusParam) filters.status = statusParam as AccessLogStatus;

        const modeParam = searchParams.get('mode');
        if (modeParam) filters.mode = modeParam as AccessLogMode;

        const dateFromParam = searchParams.get('date_from');
        if (dateFromParam) filters.date_from = dateFromParam;

        const dateToParam = searchParams.get('date_to');
        if (dateToParam) filters.date_to = dateToParam;

        const searchParam = searchParams.get('search');
        if (searchParam) filters.search = searchParam;

        const pageParam = searchParams.get('page');
        filters.page = pageParam ? Number(pageParam) : 1;

        return filters;
    }, [searchParams]);

    // A monotonic id (instead of a single boolean per effect run) so both
    // the filter-driven effect below and the manual refetch after
    // registering a card can share one loader without racing each other.
    const requestIdRef = useRef(0);

    const loadLogs = useCallback(async () => {
        const requestId = ++requestIdRef.current;
        setLoading(true);
        setError(null);

        try {
            const response = await fetchAccessLogs(buildFilters());
            if (requestIdRef.current !== requestId) return;
            setLogs(response.data);
            setMeta(response.meta);
        } catch {
            if (requestIdRef.current !== requestId) return;
            setError('Gagal memuat riwayat akses. Silakan coba lagi.');
        } finally {
            if (requestIdRef.current === requestId) setLoading(false);
        }
    }, [buildFilters]);

    useEffect(() => {
        void loadLogs();
    }, [loadLogs]);

    const goToPage = (page: number) => {
        updateParams({ page: String(page) }, false);
    };

    const columnCount = canRegisterCards ? 7 : 6;

    return (
        <div className="flex flex-1 flex-col gap-4 p-4">
            <div>
                <h1 className="text-xl font-semibold">Riwayat Akses</h1>
                <p className="text-muted-foreground text-sm">
                    Daftar setiap kartu yang discan di portal, terbaru lebih dulu.
                </p>
            </div>

            <Card>
                <CardContent className="flex flex-col gap-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="grid gap-2">
                            <Label htmlFor="filter-status">Status</Label>
                            <Select
                                value={status}
                                onValueChange={(value) =>
                                    updateParams({ status: value === 'all' ? null : value })
                                }
                            >
                                <SelectTrigger id="filter-status" className="w-full">
                                    <SelectValue placeholder="Semua status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua status</SelectItem>
                                    {STATUS_OPTIONS.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="filter-mode">Mode</Label>
                            <Select
                                value={mode}
                                onValueChange={(value) =>
                                    updateParams({ mode: value === 'all' ? null : value })
                                }
                            >
                                <SelectTrigger id="filter-mode" className="w-full">
                                    <SelectValue placeholder="Semua mode" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua mode</SelectItem>
                                    {MODE_OPTIONS.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="filter-date-from">Dari tanggal</Label>
                            <Input
                                id="filter-date-from"
                                type="date"
                                value={dateFrom}
                                max={dateTo || undefined}
                                onChange={(e) =>
                                    updateParams({ date_from: e.target.value || null })
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="filter-date-to">Sampai tanggal</Label>
                            <Input
                                id="filter-date-to"
                                type="date"
                                value={dateTo}
                                min={dateFrom || undefined}
                                onChange={(e) => updateParams({ date_to: e.target.value || null })}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="filter-search">Cari nama / UID</Label>
                            <Input
                                id="filter-search"
                                type="search"
                                placeholder="Nama pemilik atau UID kartu"
                                value={searchInput}
                                onChange={(e) => setSearchInput(e.target.value)}
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {error && (
                <Alert variant="destructive">
                    <AlertCircle />
                    <AlertTitle>Terjadi kesalahan</AlertTitle>
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="px-3 py-2 font-medium">Waktu Scan</th>
                                    <th className="px-3 py-2 font-medium">Nama Pemilik</th>
                                    <th className="px-3 py-2 font-medium">UID</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">Mode</th>
                                    <th className="px-3 py-2 font-medium">Diproses Oleh</th>
                                    {canRegisterCards && (
                                        <th className="px-3 py-2 font-medium">Aksi</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {loading &&
                                    Array.from({ length: 5 }).map((_, i) => (
                                        <tr key={i} className="border-b last:border-0">
                                            {Array.from({ length: columnCount }).map((__, j) => (
                                                <td key={j} className="px-3 py-3">
                                                    <Skeleton className="h-4 w-full" />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}

                                {!loading && logs.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={columnCount}
                                            className="text-muted-foreground px-3 py-8 text-center"
                                        >
                                            Tidak ada riwayat akses yang cocok dengan filter ini.
                                        </td>
                                    </tr>
                                )}

                                {!loading &&
                                    logs.map((log) => (
                                        <tr key={log.id} className="border-b last:border-0">
                                            <td className="px-3 py-3 whitespace-nowrap">
                                                {formatDateTime(log.scanned_at)}
                                            </td>
                                            <td className="px-3 py-3">
                                                {log.is_known_card ? (
                                                    log.owner_name
                                                ) : (
                                                    <span className="text-muted-foreground italic">
                                                        {log.owner_name}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-3 font-mono text-xs">
                                                {log.scanned_uid}
                                            </td>
                                            <td className="px-3 py-3">
                                                <Badge variant={STATUS_BADGE_VARIANT[log.status]}>
                                                    {STATUS_LABEL[log.status]}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-3">{MODE_LABEL[log.mode]}</td>
                                            <td className="px-3 py-3">
                                                {log.processed_by?.name ?? '-'}
                                            </td>
                                            {canRegisterCards && (
                                                <td className="px-3 py-3">
                                                    {!log.is_known_card && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => setRegisteringLog(log)}
                                                        >
                                                            Daftarkan Kartu Ini
                                                        </Button>
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
                    </div>

                    {meta && meta.total > 0 && (
                        <div className="flex flex-col items-center justify-between gap-3 pt-4 sm:flex-row">
                            <p className="text-muted-foreground text-sm">
                                Menampilkan {meta.from ?? 0}–{meta.to ?? 0} dari {meta.total} data
                            </p>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={meta.current_page <= 1}
                                    onClick={() => goToPage(meta.current_page - 1)}
                                >
                                    Sebelumnya
                                </Button>
                                <span className="text-sm">
                                    Halaman {meta.current_page} dari {meta.last_page}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={meta.current_page >= meta.last_page}
                                    onClick={() => goToPage(meta.current_page + 1)}
                                >
                                    Berikutnya
                                </Button>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {canRegisterCards && (
                <RegisterRfidCardDialog
                    open={registeringLog !== null}
                    onOpenChange={(open) => {
                        if (!open) setRegisteringLog(null);
                    }}
                    initialUid={registeringLog?.scanned_uid ?? ''}
                    onRegistered={() => {
                        setRegisteringLog(null);
                        void loadLogs();
                    }}
                />
            )}
        </div>
    );
}
