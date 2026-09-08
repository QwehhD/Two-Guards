/**
 * Public landing page (Tahap 7) — the only page in the app reachable
 * without logging in. Rendered directly at "/" in app.tsx, with no
 * ProtectedRoute/GuestOnlyRoute wrapper, so it stays visible to signed-out
 * visitors and signed-in staff alike.
 *
 * This is the item-1 shell: just the public route existing and working.
 * Live device status, recent activity, polling, and the login link land
 * in follow-up commits.
 */
export default function Landing() {
    return (
        <main className="bg-background text-foreground min-h-svh">
            <div className="mx-auto flex min-h-svh max-w-3xl flex-col items-center justify-center gap-4 px-6 text-center">
                <h1 className="text-3xl font-semibold tracking-tight">
                    Status Portal Parkir
                </h1>
                <p className="text-muted-foreground max-w-prose">
                    Pantau status gerbang portal secara umum dan real-time.
                    Tampilan status perangkat dan aktivitas terakhir akan
                    segera hadir di sini.
                </p>
            </div>
        </main>
    );
}
