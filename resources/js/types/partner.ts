export type PartnerStatus =
    | 'draft'
    | 'interest_submitted'
    | 'rejected'
    | 'pending_agreement'
    | 'pending_payment'
    | 'confirmed'
    | 'onboarding'
    | 'submitted'
    | 'scheduled'
    | 'finalized';

export type PackageTier =
    | 'diamond'
    | 'platinum'
    | 'gold'
    | 'silver'
    | 'cso'
    | 'exhibitor';

export type SessionFormat =
    | 'panel'
    | 'workshop'
    | 'plenary'
    | 'roundtable'
    | 'exhibition'
    | 'side_event';

export type SessionStatus =
    | 'draft'
    | 'submitted'
    | 'scheduled'
    | 'confirmed'
    | 'completed'
    | 'cancelled';

export type InvoiceStatus = 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled';

export type PaymentStatus = 'pending' | 'confirmed' | 'failed' | 'refunded';

export type ChangeRequestStatus =
    | 'pending'
    | 'approved'
    | 'rejected'
    | 'auto_resolved';

export type ChangeRequestType = 'time' | 'room' | 'session_details' | 'other';

export type AgreementStatus = 'pending' | 'signed' | 'verified';

export interface Conference {
    id: number;
    name: string;
    slug: string;
    year: number;
    start_date: string;
    end_date: string;
    venue: string;
    description: string | null;
    registration_deadline: string | null;
    onboarding_deadline: string | null;
    lock_date: string | null;
    status: string;
}

export interface ComplimentaryRegistrations {
    vip: number;
    standard: number;
    total: number;
}

export interface SponsorshipPackage {
    id: number;
    conference_id: number;
    name: string;
    slug: string;
    tier: PackageTier;
    price: number;
    currency: string;
    max_partners: number | null;
    description: string | null;
    benefits: string[] | null;
    thought_leadership: string[] | null;
    visibility: string[] | null;
    session_slots: number;
    exhibition_space: string | null;
    complimentary_registrations: ComplimentaryRegistrations | null;
    is_active: boolean;
    conference?: Conference;
}

export interface Partner {
    id: number;
    conference_id: number;
    user_id: number;
    organization_name: string;
    slug: string;
    contact_person: string;
    email: string;
    phone: string | null;
    physical_address: string | null;
    billing_address: string | null;
    tax_details: string | null;
    customer_code: string | null;
    logo_path: string | null;
    description: string | null;
    social_media: Record<string, string> | null;
    number_of_participants: number | null;
    exhibition_preferences: string | null;
    status: PartnerStatus;
    onboarding_progress: OnboardingProgress | null;
    submitted_at: string | null;
    confirmed_at: string | null;
    locked_at: string | null;
    created_at: string;
    updated_at: string;
    packages?: SponsorshipPackage[];
    sessions?: ConferenceSession[];
    invoices?: Invoice[];
    contacts?: PartnerContact[];
    agreements?: Agreement[];
    brandingRequirement?: BrandingRequirement | null;
}

export interface OnboardingProgress {
    organization: number;
    sessions: number;
    communications: number;
    contacts: number;
}

export interface Agreement {
    id: number;
    partner_id: number;
    document_path: string | null;
    signed_document_path: string | null;
    signed_by_name: string | null;
    signed_method: 'digital' | 'upload' | null;
    signed_at: string | null;
    generated_at: string | null;
    status: AgreementStatus;
}

export interface Invoice {
    id: number;
    partner_id: number;
    invoice_number: string;
    customer_code: string | null;
    document_path: string | null;
    date_of_service: string;
    due_date: string;
    amount: number;
    currency: string;
    benefits_summary: string[] | null;
    bank_details: Record<string, string> | null;
    additional_options: Record<string, unknown> | null;
    status: InvoiceStatus;
    paid_at: string | null;
    sent_at: string | null;
    notes: string | null;
    payments?: Payment[];
}

export interface Payment {
    id: number;
    invoice_id: number;
    partner_id: number;
    amount: number;
    currency: string;
    payment_method: string | null;
    transaction_reference: string | null;
    supporting_document_path: string | null;
    status: PaymentStatus;
    confirmed_at: string | null;
}

export interface SessionSlot {
    id: number;
    conference_id: number;
    slot_code: string;
    slot_category: string;
    track_label: string | null;
    day_index: number;
    date: string | null;
    time_label: string;
    start_time: string | null;
    end_time: string | null;
    default_format: string | null;
    capacity_hint: number | null;
    default_room?: { id: number; name: string } | null;
}

export interface ConferenceSession {
    id: number;
    partner_id: number;
    conference_id: number;
    title: string;
    description: string | null;
    format: SessionFormat;
    organizers: string[] | null;
    co_hosts: string[] | null;
    target_audience: string | null;
    expected_participants: number | null;
    is_open: boolean;
    special_requirements: Record<string, unknown> | null;
    status: SessionStatus;
    submitted_at: string | null;
    /** The approved slot. Null until the partnerships team signs off. */
    session_slot_id: number | null;
    /** A slot awaiting approval. Non-null means a time request is pending. */
    requested_session_slot_id: number | null;
    session_slot?: SessionSlot | null;
    requested_session_slot?: SessionSlot | null;
    pending_time_request?: ChangeRequest | null;
    schedule?: SessionSchedule;
    contacts?: SessionContact[];
}

export interface PartnerContact {
    id: number;
    partner_id: number;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    organization: string | null;
}

export interface SessionContact {
    id: number;
    conference_session_id: number;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    organization: string | null;
}

export interface Room {
    id: number;
    conference_id: number;
    name: string;
    building: string | null;
    floor: string | null;
    capacity: number;
    format_suitability: string[] | null;
    equipment: Record<string, unknown> | null;
    is_active: boolean;
}

export interface TimeSlot {
    id: number;
    conference_id: number;
    date: string;
    start_time: string;
    end_time: string;
    label: string | null;
    slot_type: string;
}

export interface SessionSchedule {
    id: number;
    conference_session_id: number;
    room_id: number;
    time_slot_id: number;
    status: string;
    notes: string | null;
    room?: Room;
    time_slot?: TimeSlot;
    session?: ConferenceSession;
    resource_assignments?: ResourceAssignment[];
}

export interface ResourceAssignment {
    id: number;
    session_schedule_id: number;
    user_id: number | null;
    resource_type: string;
    name: string | null;
    email: string | null;
}

export interface ChangeRequest {
    id: number;
    conference_session_id: number;
    partner_id: number;
    type: ChangeRequestType;
    current_value: Record<string, unknown> | null;
    requested_value: Record<string, unknown> | null;
    reason: string | null;
    status: ChangeRequestStatus;
    requested_by: number | null;
    reviewed_by: number | null;
    reviewed_at: string | null;
    resolution_notes: string | null;
    created_at: string;
    updated_at: string;
    session?: ConferenceSession;
    partner?: Partner;
    requested_by_user?: { id: number; name: string } | null;
    reviewed_by_user?: { id: number; name: string } | null;
}

export interface FeedbackSurvey {
    id: number;
    partner_id: number;
    conference_id: number;
    conference_session_id: number | null;
    survey_template: Record<string, unknown> | null;
    responses: Record<string, unknown> | null;
    submitted_at: string | null;
}

export interface BrandingRequirement {
    id: number;
    partner_id: number;
    requirements: string | null;
    media_contact_name: string | null;
    media_contact_email: string | null;
    media_contact_phone: string | null;
    assets: string[] | null;
}
