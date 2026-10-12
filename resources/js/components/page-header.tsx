import type { ReactNode } from 'react';
import { Marker } from '@/components/marker';

type Props = {
    /** Small label above the title: which part of the app this page belongs to. */
    eyebrow: string;
    title: string;
    description?: ReactNode;
    /** Buttons on the right, e.g. "Tambah Kartu". */
    actions?: ReactNode;
};

/** Title block shared by every page inside the signed-in app. */
export function PageHeader({ eyebrow, title, description, actions }: Props) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-4 border-b pb-4">
            <div className="flex flex-col gap-2">
                <span className="label-mono text-muted-foreground flex items-center gap-2">
                    <Marker />
                    {eyebrow}
                </span>
                <h1 className="font-mono text-2xl font-bold tracking-tight uppercase">{title}</h1>
                {description && <p className="text-muted-foreground max-w-prose text-sm">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </div>
    );
}
