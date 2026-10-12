import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { deleteRfidCard } from '@/services/rfidCardService';
import type { RfidCard } from '@/types';

type Props = {
    /** The card to delete; null while closed. */
    card: RfidCard | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: (card: RfidCard) => void;
};

/**
 * Confirms deleting a card. Its access logs stay, but lose the link to the
 * card (rfid_card_id is nulled on delete), so the history shows them as
 * "Tidak Dikenal" from then on — hence the nudge towards Nonaktif instead.
 */
export function DeleteRfidCardDialog({ card, open, onOpenChange, onDeleted }: Props) {
    const [processing, setProcessing] = useState(false);

    const handleDelete = async () => {
        if (!card) return;

        setProcessing(true);

        try {
            await deleteRfidCard(card.id);
            toast.success(`Kartu "${card.owner_name}" dihapus.`);
            onDeleted?.(card);
            onOpenChange(false);
        } catch (error: any) {
            toast.error(error.response?.data?.message ?? 'Gagal menghapus kartu. Silakan coba lagi.');
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hapus Kartu RFID</DialogTitle>
                    <DialogDescription>
                        Hapus kartu milik <span className="font-medium">{card?.owner_name}</span> (UID{' '}
                        <span className="font-mono">{card?.uid}</span>)? Riwayat aksesnya tetap tersimpan, tetapi
                        akan tampil sebagai "Tidak Dikenal", dan kartu ini ditolak kalau discan lagi. Kalau kartu
                        hanya tidak boleh dipakai sementara, ubah statusnya menjadi Nonaktif.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Batal</Button>
                    </DialogClose>
                    <Button variant="destructive" disabled={processing} onClick={handleDelete}>
                        {processing && <Spinner />}
                        Hapus Kartu
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
