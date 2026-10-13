import { ArrowLeft } from 'lucide-react';
import { Link } from 'react-router-dom';
import AppLogoIcon from '@/components/app-logo-icon';
import { Marker } from '@/components/marker';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="bg-grid flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="bg-card w-full max-w-sm border p-8">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link to="/" className="flex flex-col items-center gap-2">
                            <div className="bg-primary text-primary-foreground flex size-12 items-center justify-center rounded-md">
                                <AppLogoIcon className="size-7 fill-current" />
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <span className="label-mono text-muted-foreground flex items-center justify-center gap-2">
                                <Marker />
                                Portal parkir
                            </span>
                            <h1 className="font-mono text-xl font-bold tracking-tight uppercase">{title}</h1>
                            <p className="text-muted-foreground text-center text-sm">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
            <Link
                to="/"
                className="label-mono text-muted-foreground hover:text-foreground flex items-center gap-2 transition-colors"
            >
                <ArrowLeft className="size-4" />
                Kembali ke beranda
            </Link>
        </div>
    );
}
