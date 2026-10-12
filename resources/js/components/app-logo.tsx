import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const name = import.meta.env.VITE_APP_NAME ?? 'ParkAutomation';

    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                <AppLogoIcon className="size-5 fill-current" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate font-mono text-sm leading-tight font-bold tracking-wider uppercase">
                    {name}
                </span>
            </div>
        </>
    );
}
