import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { updateRfidCard } from '@/services/rfidCardService';
import type { RfidCard, RfidCardStatus } from '@/types';

type Props = {
    /** The card being edited; null while closed. */
    card: RfidCard | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onUpdated?: (card: RfidCard) => void;
};

/**
 * Edits a registered card's UID, owner and status. Setting it to Nonaktif is
 * the way to stop a card from opening the gate while keeping its history.
 */
export function EditRfidCardDialog({ card, open, onOpenChange, onUpdated }: Props) {
    const [uid, setUid] = useState('');
    const [ownerName, setOwnerName] = useState('');
    const [status, setStatus] = useState<RfidCardStatus>('active');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    // Re-seed from the card each time the dialog opens, so a cancelled edit
    // doesn't leave stale input behind for the next card.
    useEffect(() => {
        if (open && card) {
            setUid(card.uid);
            setOwnerName(card.owner_name);
            setStatus(card.status);
            setErrors({});
        }
    }, [open, card]);

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();
        if (!card) return;

        setProcessing(true);
        setErrors({});

        try {
            const updated = await updateRfidCard(card.id, { uid, owner_name: ownerName, status });
            toast.success(`Kartu "${updated.owner_name}" disimpan.`);
            onUpdated?.(updated);
            onOpenChange(false);
        } catch (error: any) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
            } else {
                toast.error('Gagal menyimpan kartu. Silakan coba lagi.');
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Kartu RFID</DialogTitle>
                    <DialogDescription>
                        Kartu nonaktif tetap tercatat setiap kali discan, tetapi tidak pernah membuka palang.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="edit-card-uid">UID Kartu</Label>
                        <Input id="edit-card-uid" required value={uid} onChange={(e) => setUid(e.target.value)} />
                        <InputError message={errors.uid?.[0]} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit-card-owner-name">Nama Pemilik</Label>
                        <Input
                            id="edit-card-owner-name"
                            required
                            value={ownerName}
                            onChange={(e) => setOwnerName(e.target.value)}
                        />
                        <InputError message={errors.owner_name?.[0]} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit-card-status">Status</Label>
                        <Select value={status} onValueChange={(value) => setStatus(value as RfidCardStatus)}>
                            <SelectTrigger id="edit-card-status" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Aktif</SelectItem>
                                <SelectItem value="inactive">Nonaktif</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status?.[0]} />
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
