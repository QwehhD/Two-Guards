import { CheckCircle2, Clock, XCircle, type LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { AccessLogStatus, PublicAccessLog } from '@/types';

const STATUS_LABEL: Record<AccessLogStatus, string> = {
    approved: 'Akses disetujui',
    denied: 'Akses ditolak',
    pending: 'Menunggu persetujuan',
    expired: 'Akses kedaluwarsa',
};

const STATUS_ICON: Record<AccessLogStatus, LucideIcon> = {
    approved: CheckCircle2,
    denied: XCircle,
    pending: Clock,
    expired: Clock,
};

const STATUS_COLOR: Record<AccessLogStatus, string> = {
    approved: 'text-primary',
    denied: 'text-destructive',
    pending: 'text-amber-600 dark:text-amber-400',
    expired: 'text-muted-foreground',
};

type Props = {
    activity: PublicAccessLog[];
};

/**
 * Deliberately general, per the public data contract: only a status label,
 * a relative timestamp, and the mode — never who scanned in or who
 * approved it (that data never even reaches the frontend; see
 * PublicAccessLogResource on the backend).
 *
 * Drawn as a row of grid cells, one per scan, newest first.
 */
export function RecentActivityList({ activity }: Props) {
    if (activity.length === 0) {
        return <p className="label-mono text-muted-foreground px-6 py-10 text-center">Belum ada aktivitas.</p>;
    }

    return (
        <ul className="grid sm:grid-cols-2 lg:grid-cols-5">
            {activity.map((entry, index) => {
                const Icon = STATUS_ICON[entry.status];

                return (
                    <li key={index} className="bg-card flex flex-col gap-4 border-b p-6 sm:border-r">
                        <div className="flex items-center justify-between gap-2">
                            <Icon className={cn('size-6 shrink-0', STATUS_COLOR[entry.status])} />
                            <Badge variant={entry.mode === 'auto' ? 'default' : 'secondary'}>
                                {entry.mode === 'auto' ? 'Otomatis' : 'Manual'}
                            </Badge>
                        </div>
                        <span className="font-mono text-sm font-bold uppercase">{STATUS_LABEL[entry.status]}</span>
                        {entry.scanned_at && <span className="label-mono text-muted-foreground">{entry.scanned_at}</span>}
                    </li>
                );
            })}
        </ul>
    );
}
