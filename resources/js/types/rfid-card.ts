export type RfidCardStatus = 'active' | 'inactive';

export type RfidCard = {
    id: number;
    uid: string;
    owner_name: string;
    status: RfidCardStatus;
    created_by: number | null;
    created_at: string;
    updated_at: string;
};

export type CreateRfidCardPayload = {
    uid: string;
    owner_name: string;
    status: RfidCardStatus;
};
