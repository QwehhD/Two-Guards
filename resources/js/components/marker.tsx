import { cn } from '@/lib/utils';

/** The small orange square that marks labels and corners throughout the design. */
export function Marker({ className }: { className?: string }) {
    return <span aria-hidden className={cn('bg-primary inline-block size-2 shrink-0', className)} />;
}
