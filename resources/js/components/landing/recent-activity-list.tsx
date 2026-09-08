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
    approved: 'text-emerald-600 dark:text-emerald-400',
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
 */
export function RecentActivityList({ activity }: Props) {
    if (activity.length === 0) {
        return (
            <p className="text-muted-foreground text-center text-sm">
                Belum ada aktivitas.
            </p>
        );
    }

    return (
        <ul className="divide-border overflow-hidden rounded-xl border">
            {activity.map((entry, index) => {
                const Icon = STATUS_ICON[entry.status];

                return (
                    <li
                        key={index}
                        className={cn(
                            'flex items-center gap-3 px-4 py-3',
                            index > 0 && 'border-border border-t',
                        )}
                    >
                        <Icon
                            className={cn('size-5 shrink-0', STATUS_COLOR[entry.status])}
                        />
                        <span className="flex-1 text-sm">
                            {STATUS_LABEL[entry.status]}
                            {entry.scanned_at && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    — {entry.scanned_at}
                                </span>
                            )}
                        </span>
                        <Badge
                            variant={entry.mode === 'auto' ? 'default' : 'secondary'}
                            className="shrink-0"
                        >
                            {entry.mode === 'auto' ? 'Otomatis' : 'Manual'}
                        </Badge>
                    </li>
                );
            })}
        </ul>
    );
}
