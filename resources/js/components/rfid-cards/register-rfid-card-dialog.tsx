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
import { createRfidCard } from '@/services/rfidCardService';
import type { RfidCard, RfidCardStatus } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Pre-fills the uid field, e.g. from an unknown scan's scanned_uid. */
    initialUid?: string;
    onRegistered?: (card: RfidCard) => void;
};

/**
 * Reusable "register an RFID card" form, shown in a dialog. Used here to
 * register a card straight from an unknown access log row, and intended to
 * be reused as-is by the standalone RFID card management page later.
 */
export function RegisterRfidCardDialog({ open, onOpenChange, initialUid = '', onRegistered }: Props) {
    const [uid, setUid] = useState(initialUid);
    const [ownerName, setOwnerName] = useState('');
    const [status, setStatus] = useState<RfidCardStatus>('active');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    // Re-seed the form each time the dialog opens for a (possibly
    // different) unknown log row, rather than carrying over stale input.
    useEffect(() => {
        if (open) {
            setUid(initialUid);
            setOwnerName('');
            setStatus('active');
            setErrors({});
        }
    }, [open, initialUid]);

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const card = await createRfidCard({ uid, owner_name: ownerName, status });
            toast.success(`Kartu "${card.owner_name}" berhasil didaftarkan.`);
            onRegistered?.(card);
            onOpenChange(false);
        } catch (error: any) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
            } else {
                toast.error('Gagal mendaftarkan kartu. Silakan coba lagi.');
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Daftarkan Kartu RFID</DialogTitle>
                    <DialogDescription>
                        Kartu ini pernah discan tapi belum terdaftar. Isi nama pemiliknya untuk
                        mendaftarkan.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="card-uid">UID Kartu</Label>
                        <Input
                            id="card-uid"
                            required
                            autoFocus
                            value={uid}
                            onChange={(e) => setUid(e.target.value)}
                        />
                        <InputError message={errors.uid?.[0]} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="card-owner-name">Nama Pemilik</Label>
                        <Input
                            id="card-owner-name"
                            required
                            placeholder="Nama karyawan"
                            value={ownerName}
                            onChange={(e) => setOwnerName(e.target.value)}
                        />
                        <InputError message={errors.owner_name?.[0]} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="card-status">Status</Label>
                        <Select value={status} onValueChange={(value) => setStatus(value as RfidCardStatus)}>
                            <SelectTrigger id="card-status" className="w-full">
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
                            Daftarkan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
