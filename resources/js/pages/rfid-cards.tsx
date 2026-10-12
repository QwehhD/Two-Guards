import { AlertCircle, Pencil, Plus, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { DeleteRfidCardDialog } from '@/components/rfid-cards/delete-rfid-card-dialog';
import { EditRfidCardDialog } from '@/components/rfid-cards/edit-rfid-card-dialog';
import { RegisterRfidCardDialog } from '@/components/rfid-cards/register-rfid-card-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { fetchRfidCards } from '@/services/rfidCardService';
import type { RfidCard, RfidCardStatus } from '@/types';

const STATUS_LABELS: Record<RfidCardStatus, string> = {
    active: 'Aktif',
    inactive: 'Nonaktif',
};

const COLUMN_COUNT = 5;

function sortByOwner(cards: RfidCard[]): RfidCard[] {
    return [...cards].sort((a, b) => a.owner_name.localeCompare(b.owner_name, 'id'));
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', { dateStyle: 'medium' });
}

/**
 * Admin-only RFID card management (the route and /api/rfid-cards both
 * enforce that): register, edit and delete the cards the portal recognises.
 */
export default function RfidCards() {
    const [cards, setCards] = useState<RfidCard[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [registering, setRegistering] = useState(false);
    // The card being edited; also the edit dialog's open state.
    const [editing, setEditing] = useState<RfidCard | null>(null);
    // The card about to be deleted; also the confirmation dialog's open state.
    const [deleting, setDeleting] = useState<RfidCard | null>(null);

    const loadCards = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            setCards(sortByOwner(await fetchRfidCards()));
        } catch {
            setError('Gagal memuat daftar kartu.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadCards();
    }, [loadCards]);

    return (
        <div className="flex flex-1 flex-col gap-4 p-4">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-xl font-semibold">Kartu RFID</h1>
                    <p className="text-muted-foreground text-sm">
                        Kartu yang dikenali portal. Kartu nonaktif tetap tercatat setiap kali discan, tetapi
                        selalu ditolak.
                    </p>
                </div>
                <Button onClick={() => setRegistering(true)}>
                    <Plus /> Tambah Kartu
                </Button>
            </div>

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
                                    <th className="px-3 py-2 font-medium">UID</th>
                                    <th className="px-3 py-2 font-medium">Pemilik</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">Didaftarkan</th>
                                    <th className="px-3 py-2 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {loading &&
                                    Array.from({ length: 3 }).map((_, i) => (
                                        <tr key={i} className="border-b last:border-0">
                                            {Array.from({ length: COLUMN_COUNT }).map((__, j) => (
                                                <td key={j} className="px-3 py-3">
                                                    <Skeleton className="h-4 w-full" />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}

                                {!loading && cards.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={COLUMN_COUNT}
                                            className="text-muted-foreground px-3 py-8 text-center"
                                        >
                                            Belum ada kartu terdaftar.
                                        </td>
                                    </tr>
                                )}

                                {!loading &&
                                    cards.map((card) => (
                                        <tr key={card.id} className="border-b last:border-0">
                                            <td className="px-3 py-3 font-mono">{card.uid}</td>
                                            <td className="px-3 py-3 font-medium">{card.owner_name}</td>
                                            <td className="px-3 py-3">
                                                <Badge variant={card.status === 'active' ? 'default' : 'secondary'}>
                                                    {STATUS_LABELS[card.status]}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-3">{formatDate(card.created_at)}</td>
                                            <td className="px-3 py-3">
                                                <div className="flex flex-wrap gap-2">
                                                    <Button size="sm" variant="outline" onClick={() => setEditing(card)}>
                                                        <Pencil /> Edit
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="text-destructive"
                                                        onClick={() => setDeleting(card)}
                                                    >
                                                        <Trash2 /> Hapus
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <RegisterRfidCardDialog
                open={registering}
                onOpenChange={setRegistering}
                onRegistered={(card) => setCards((prev) => sortByOwner([...prev, card]))}
            />

            <EditRfidCardDialog
                card={editing}
                open={editing !== null}
                onOpenChange={(open) => {
                    if (!open) setEditing(null);
                }}
                onUpdated={(updated) =>
                    setCards((prev) => sortByOwner(prev.map((card) => (card.id === updated.id ? updated : card))))
                }
            />

            <DeleteRfidCardDialog
                card={deleting}
                open={deleting !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleting(null);
                }}
                onDeleted={(deleted) => setCards((prev) => prev.filter((card) => card.id !== deleted.id))}
            />
        </div>
    );
}
