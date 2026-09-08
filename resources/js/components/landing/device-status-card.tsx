import { Lock, LockOpen } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { PublicDeviceStatus } from '@/types';

type Props = {
    device: PublicDeviceStatus;
};

/**
 * One device's public status tile on the landing page. All color/icon
 * transitions are driven by plain CSS (`transition-colors`) plus a
 * `key`-remount on the portal icon+label group so tw-animate-css's
 * enter animation replays whenever portal_status flips — no animation
 * library needed for this "make it feel alive" requirement.
 */
export function DeviceStatusCard({ device }: Props) {
    const isOpen = device.portal_status === 'open';
    const isOnline = device.status === 'online';

    return (
        <Card
            className={cn(
                'transition-colors duration-500',
                isOpen
                    ? 'border-emerald-500/40 bg-emerald-50/60 dark:bg-emerald-950/20'
                    : 'bg-card',
            )}
        >
            <CardHeader>
                <div className="flex items-center justify-between gap-2">
                    <CardTitle className="text-base">{device.name}</CardTitle>
                    <span
                        className={cn(
                            'flex items-center gap-1.5 text-xs font-medium transition-colors duration-500',
                            isOnline
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-muted-foreground',
                        )}
                    >
                        <span
                            className={cn(
                                'size-2 rounded-full transition-colors duration-500',
                                isOnline ? 'bg-emerald-500' : 'bg-muted-foreground/60',
                            )}
                        />
                        {isOnline ? 'Online' : 'Offline'}
                    </span>
                </div>
            </CardHeader>
            <CardContent className="flex items-center justify-between gap-3">
                <div
                    key={device.portal_status}
                    className="animate-in fade-in zoom-in-95 flex items-center gap-2 duration-500"
                >
                    {isOpen ? (
                        <LockOpen className="size-6 text-emerald-600 dark:text-emerald-400" />
                    ) : (
                        <Lock className="text-muted-foreground size-6" />
                    )}
                    <span className="font-medium">
                        {isOpen ? 'Terbuka' : 'Tertutup'}
                    </span>
                </div>

                <Badge variant={device.mode === 'auto' ? 'default' : 'secondary'}>
                    {device.mode === 'auto' ? 'Otomatis' : 'Manual'}
                </Badge>
            </CardContent>
        </Card>
    );
}
