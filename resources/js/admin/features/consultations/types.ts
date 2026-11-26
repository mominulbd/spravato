/** Shapes of /api/v1/admin/consultation-requests (docs/API.md §8). */
export type ConsultationStatus = 'new' | 'contacted' | 'scheduled' | 'closed' | 'spam';

export interface Option {
    value: string;
    label: string;
}

export interface StaffRef {
    id: number;
    name: string;
    initials: string;
}

export interface ConsultationRow {
    id: number;
    reference: string;
    topic: Option;
    status: ConsultationStatus;
    is_overdue: boolean;
    waiting_minutes: number | null;
    assigned_to: StaffRef | null;
    follow_up_at: string | null;
    received_at: string;
    contacted_at: string | null;
    scheduled_at: string | null;
    closed_at: string | null;
    source_page: string | null;
    /** Label of the website button that opened the dialog. */
    cta: string | null;
    origin: 'form';
    allowed_statuses: ConsultationStatus[];
    /** Masked: "Daniel R. · (253) •••-0198". */
    contact: string;
}

export interface ConsultationActivity {
    id: number;
    action: string;
    label: string;
    user: { id: number; name: string } | null;
    created_at: string;
}

export interface ConsultationNote {
    id: number;
    body: string;
    author: StaffRef | null;
    created_at: string;
}

/** The decrypted request, as the detail screen (Figma 05) shows it. */
export interface ConsultationDetail extends Omit<ConsultationRow, 'contact'> {
    name: string;
    phone: string;
    phone_href: string;
    email: string | null;
    message: string | null;
    best_time: Option | null;
    insurance: string | null;
    contact_method: Option | null;
    patient_status: Option | null;
    emergency_acknowledged: boolean;
    source: { page: string | null; cta: string | null; previous_page: string | null; first_touch: string; utm: Record<string, string> | null; device: string | null };
    privacy: { view_count: number; deletes_on: string; retention_days: number };
    notes: ConsultationNote[];
    activity: ConsultationActivity[];
}

export type StatusCounts = Record<'all' | ConsultationStatus, number>;

export interface ConsultationListMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
    counts: StatusCounts;
    topics: Option[];
    assignees: StaffRef[];
    can: { update: boolean; assign: boolean; export: boolean; delete: boolean };
}

export interface ConsultationOverview {
    range_days: number;
    queue: {
        waiting: number;
        waiting_over_day: number;
        overdue: number;
        longest: { id: number; reference: string; topic: Option; waiting_minutes: number; progress: number | null; is_overdue: boolean }[];
    };
    new_requests: { count: number; previous: number; change_percent: number | null };
    daily: { date: string; count: number }[];
    topics: (Option & { count: number; percent: number })[];
    recent: ConsultationRow[];
}
