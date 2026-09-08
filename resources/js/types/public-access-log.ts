import type { AccessLogMode, AccessLogStatus } from './access-log';

// Mirrors App\Http\Resources\PublicAccessLogResource — deliberately its
// own type, separate from AccessLog, matching the backend's separate
// resource class. Only status/mode/a relative timestamp: no owner name,
// UID, or who processed it, ever.
export type PublicAccessLog = {
    status: AccessLogStatus;
    mode: AccessLogMode;
    scanned_at: string | null;
};
