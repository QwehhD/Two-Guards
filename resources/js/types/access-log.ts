export type AccessLogStatus = 'approved' | 'denied' | 'pending' | 'expired';

export type AccessLogMode = 'auto' | 'manual';

// Mirrors App\Http\Resources\AccessLogResource. owner_name always has a
// value ("Tidak Dikenal" for an unregistered card), so the UI never has to
// null-check it directly — check is_known_card instead when behavior (not
// just display) depends on whether the card is registered.
export type AccessLog = {
    id: number;
    scanned_uid: string;
    is_known_card: boolean;
    rfid_card_id: number | null;
    owner_name: string;
    mode: AccessLogMode;
    status: AccessLogStatus;
    device: { id: number; name: string } | null;
    processed_by: { id: number; name: string } | null;
    scanned_at: string | null;
    processed_at: string | null;
};

export type AccessLogFilters = {
    status?: AccessLogStatus;
    mode?: AccessLogMode;
    device_id?: number;
    date_from?: string;
    date_to?: string;
    search?: string;
    page?: number;
    per_page?: number;
};

export type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
};

export type PaginatedResponse<T> = {
    data: T[];
    meta: PaginationMeta;
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
};
