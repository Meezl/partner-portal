# AHAIC 2027 — Partner Sponsorship, Onboarding, Scheduling & Conference Management Portal

## Implementation Plan

## Execution Status

- [x] Expression of Interest workflow verified
    - Draft save, initial submission, rejected-submission reopen path
    - Covered by backend feature tests and frontend workflow-state tests
- [x] Commitment, Agreement, and Invoice workflow verified
    - Package confirmation, agreement generation, digital sign/upload path, invoice generation
    - Covered by backend feature tests and frontend workflow-state tests
- [x] Payment and Confirmation workflow verified
    - Payment proof submission, finance confirmation, invoice paid transition, partner confirmation
    - Covered by backend feature tests
- [ ] Public package browsing module not yet verified
- [x] Onboarding module verified
    - Organization profile, communications and branding, contacts, session submissions, progress calculation
    - Covered by backend onboarding/submission feature tests and frontend onboarding workflow tests
- [x] Final submission module verified
    - Review page, completeness validation, submission lock, internal/partner notifications, session submission transition
    - Covered by backend onboarding/submission feature tests and frontend onboarding workflow tests
- [x] Scheduling and allocation module verified
    - Scheduling board, workbook-style room allocation matrix, room fit validation, conflict detection, room management, resource assignment
    - Covered by backend admin scheduling feature tests and frontend scheduling workflow tests
- [ ] Change request module not yet verified
- [ ] Finalization module not yet verified
- [ ] Live conference and reporting module not yet verified

---

## Phase 6 Detailed Track

- [x] Room allocation matrix foundation
    - Daily matrix view, slot load summaries, room utilization stats, room suitability surfacing
- [x] Room allocation validation
    - Prevent over-capacity room assignments and unsupported format assignments
- [ ] Booth inventory normalization
    - Convert booth ranges/zones into assignable booth records and statuses
- [ ] Booth allocation matrix
    - Admin assignment board for booth zones, numbers, packages, and occupancy
- [ ] Spreadsheet import and reconciliation flow
    - Map workbook matrix rows into room/booth inventory and draft allocations safely

---

## 1. Project Overview

The AHAIC Partner Portal is a full-stack web application that manages the end-to-end lifecycle of conference partner engagement — from initial expression of interest through sponsorship commitment, onboarding, session scheduling, conference execution, and post-event reporting.

**Tech Stack:**

- **Backend:** Laravel 13, PHP 8.3, MySQL
- **Frontend:** Vue 3, TypeScript, Inertia.js, Tailwind CSS v4, shadcn-vue
- **Auth:** Laravel Fortify (multi-factor ready)
- **Real-time:** Laravel Echo + Pusher/Reverb (dashboards)
- **PDF Generation:** DomPDF / Snappy
- **Queue:** Database driver (upgradeable to Redis)

---

## 2. Brand Guidelines Integration

### Colors (from AHAIC 2025 Brand Guide)

| Token               | Name             | Hex       | Usage                         |
| ------------------- | ---------------- | --------- | ----------------------------- |
| `--ahaic-green`     | Land Green       | `#255325` | Primary, headings, CTAs       |
| `--ahaic-offwhite`  | Off White        | `#eee7e1` | Backgrounds, cards            |
| `--ahaic-yellow`    | Sunshine Yellow  | `#ffa43b` | Accents, warnings, highlights |
| `--ahaic-red`       | Independence Red | `#ff0a0a` | Destructive actions, alerts   |
| `--ahaic-blue`      | Ocean Blue       | `#0a9fa5` | Links, info states            |
| `--ahaic-lightblue` | Light Blue       | `#b3dbe4` | Secondary backgrounds         |
| `--ahaic-brown`     | Earth Brown      | `#553a1c` | Subtle accents                |

### Typography

| Role      | Font      | Source       | Weight          |
| --------- | --------- | ------------ | --------------- |
| Headlines | **Ojuju** | Google Fonts | 650 (Semi-Bold) |
| Body      | **Arimo** | Google Fonts | 400 (Regular)   |

### Design Principles

- African border-inspired geometric patterns as decorative elements
- Bold black-and-white portraiture photography style
- Vibrant 5-color combinations per asset for visual energy
- Dark mode supported with inverted palette

---

## 3. User Roles & Access Control

| Role             | Scope          | Description                                |
| ---------------- | -------------- | ------------------------------------------ |
| `super_admin`    | Global         | Full system access, user management        |
| `admin`          | Global         | Manage all partners, scheduling, reports   |
| `finance`        | Internal       | Payment verification, invoice management   |
| `programme`      | Internal (GHS) | Session scheduling, agenda management      |
| `pco`            | Internal (PCO) | Room allocation, logistics, resources      |
| `communications` | Internal       | Branding review, media coordination        |
| `partnerships`   | Internal       | Partner oversight, relationship management |
| `partner`        | Partner Org    | Organization-scoped, onboarding & sessions |

---

## 4. Database Schema

### Core Entities

#### `conferences`

```
id, name, slug, year, start_date, end_date, venue, description,
registration_deadline, onboarding_deadline, lock_date,
status (draft|active|locked|completed), settings (JSON), timestamps
```

#### `sponsorship_packages`

```
id, conference_id, name, slug, tier (gold|silver|cso|exhibitor),
price, currency, max_partners, description, benefits (JSON),
session_slots, exhibition_space, is_active, sort_order, timestamps
```

#### `partners`

```
id, conference_id, user_id (owner), organization_name, slug,
contact_person, email, phone, physical_address,
billing_address, tax_details, customer_code,
logo_path, description, social_media (JSON),
number_of_participants, exhibition_preferences (TEXT),
status (draft|interest_submitted|pending_agreement|pending_payment|
        confirmed|onboarding|submitted|scheduled|finalized),
onboarding_progress (JSON — tracks % per section),
submitted_at, confirmed_at, locked_at, timestamps, soft_deletes
```

#### `partner_package` (pivot)

```
id, partner_id, sponsorship_package_id, confirmed_at, timestamps
```

#### `agreements`

```
id, partner_id, document_path, signed_document_path,
signed_at, generated_at, status (pending|signed|verified), timestamps
```

#### `invoices`

```
id, partner_id, invoice_number, customer_code,
date_of_service, due_date, amount, currency,
benefits_summary (JSON), bank_details (JSON),
additional_options (JSON), status (draft|sent|paid|overdue|cancelled),
paid_at, sent_at, notes, timestamps
```

#### `payments`

```
id, invoice_id, partner_id, amount, currency,
payment_method, transaction_reference,
status (pending|confirmed|failed|refunded),
confirmed_by (user_id), confirmed_at, timestamps
```

#### `sessions`

```
id, partner_id, conference_id, title, description,
format (panel|workshop|plenary|roundtable|exhibition|side_event),
organizers (JSON), co_hosts (JSON),
target_audience, expected_participants (int),
is_open (bool), special_requirements (JSON — AV, translation, seating, catering),
session_lead_id (contact), communications_lead_id (contact),
status (draft|submitted|scheduled|confirmed|completed|cancelled),
submitted_at, timestamps, soft_deletes
```

#### `session_contacts`

```
id, session_id, name, email, phone, role, organization, timestamps
```

#### `partner_contacts`

```
id, partner_id, name, email, phone, role (session_lead|comms_lead|additional),
organization, timestamps
```

#### `rooms`

```
id, conference_id, name, building, floor, capacity,
format_suitability (JSON), equipment (JSON — AV, translation booths),
is_active, timestamps
```

#### `time_slots`

```
id, conference_id, date, start_time, end_time, label,
slot_type (session|break|keynote|networking), timestamps
```

#### `session_schedules`

```
id, session_id, room_id, time_slot_id,
assigned_by (user_id), status (pending|scheduled|confirmed|changed),
notes, timestamps
```

#### `resource_assignments`

```
id, session_schedule_id, user_id (nullable),
resource_type (rapporteur|session_lead|moderator|av_technician),
name, email, assigned_by, timestamps
```

#### `change_requests`

```
id, session_id, partner_id, requested_by (user_id),
type (time|room|session_details|other),
current_value (JSON), requested_value (JSON), reason,
status (pending|approved|rejected|auto_resolved),
reviewed_by (user_id), reviewed_at, resolution_notes, timestamps
```

#### `audit_logs`

```
id, auditable_type, auditable_id, user_id,
action (created|updated|deleted|status_changed|scheduled|locked),
old_values (JSON), new_values (JSON), ip_address, timestamps
```

#### `notifications_log`

```
id, user_id, partner_id, type, channel (email|portal|sms),
subject, body, sent_at, read_at, timestamps
```

#### `feedback_surveys`

```
id, partner_id, conference_id, session_id (nullable),
survey_template (JSON), responses (JSON),
submitted_at, timestamps
```

#### `branding_requirements`

```
id, partner_id, requirements (TEXT),
media_contact_name, media_contact_email, media_contact_phone,
assets (JSON — uploaded files), timestamps
```

### Extended Users Table

Add to existing `users` table:

```
role (enum), partner_id (nullable FK), phone, department,
is_active, last_login_at
```

---

## 5. Phase-by-Phase Implementation

### Phase 0 & 1: Discovery & Expression of Interest

**Backend:**

- `SponsorshipPackageController` — list packages, show details
- `ExpressionOfInterestController` — create/update/submit interest
- Save & Resume: draft persistence with `status = draft`
- Auto-email on submission to AHAIC central email

**Frontend Pages:**

- `/packages` — Public sponsorship opportunities listing
- `/packages/{slug}` — Package detail with "Express Interest" CTA
- `/partner/register` — EOI form (org name, contact, email, phone, address)
- `/partner/dashboard` — Partner home (draft resume, status tracker)

**Automations:**

- Email notification on EOI submission
- Status update → `interest_submitted`
- Session tracking (portal logins, page views)

---

### Phase 2: Package Commitment, Agreement & Invoice

**Backend:**

- `AgreementController` — generate, download, upload signed agreement
- `InvoiceController` — auto-generate invoice, send via email
- PDF generation for agreements and invoices (DomPDF)

**Frontend Pages:**

- `/partner/commitment` — Confirm package, enter billing/tax details
- `/partner/agreement` — View/download/upload signed agreement
- `/partner/invoices` — View invoices, payment instructions

**Automations:**

- Auto-generate pre-filled agreement PDF (partner name, dates, address)
- Auto-generate invoice with all financial details
- Email invoice + payment instructions
- Status → `pending_payment`

---

### Phase 3: Payment & Confirmation

**Backend:**

- `PaymentController` — record payment, confirm (manual + auto)
- Finance dashboard for payment verification
- Webhook/integration point for payment gateway

**Frontend Pages:**

- `/partner/payment` — Payment upload/confirmation
- `/admin/finance/payments` — Finance team payment management

**Automations:**

- Payment confirmation triggers onboarding unlock
- Send confirmation email with partner-specific login
- Status → `confirmed`

---

### Phase 4: Partner Onboarding (Save & Resume)

**Backend:**

- `OnboardingController` — section-by-section save, progress tracking
- `SessionController` — CRUD for partner sessions
- `BrandingController` — branding requirements submission
- `ContactController` — manage partner contacts
- File upload handling (logos, documents)

**Frontend Pages:**

- `/partner/onboarding` — Multi-step wizard with sections:
    1. Organization Profile (logo, description, social, participants, exhibition)
    2. Session Submission (title, description, format, organizers, audience, requirements)
    3. Communications & Branding (branding reqs, media contacts)
    4. Contacts (session lead, comms lead, additional)
- Progress indicator (% completion per section)
- Save & Resume at any point

**Automations:**

- Track completion % per section in `onboarding_progress` JSON
- Validate mandatory fields, alert for missing data
- Notify internal teams when partner completes sections
- Status remains `confirmed` until all sections done

---

### Phase 5: Final Submission

**Backend:**

- `SubmissionController` — lock inputs, route to internal teams
- Validation of all required fields before allowing submission

**Frontend Pages:**

- `/partner/review` — Full review of all submitted data
- `/partner/submission` — Submit confirmation with lock warning

**Automations:**

- Lock inputs (2 weeks before conference or on manual trigger)
- Auto-route to Programme (GHS), PCO, Partnerships, central email
- Status → `submitted`

---

### Phase 6: Internal Planning & Allocation

**Backend:**

- `SchedulingController` — session slot assignment
- `RoomAllocationController` — room assignment by PCO
- `ResourceAssignmentController` — rapporteurs, session leads
- `AgendaController` — master + partner-specific agenda generation
- `ConflictDetectionService` — overlap detection, suggestions

**Frontend Pages:**

- `/admin/scheduling` — Drag-and-drop scheduling board
- `/admin/scheduling/rooms` — Room allocation grid
- `/admin/scheduling/resources` — Resource assignment
- `/admin/scheduling/agenda` — Master agenda view (day-by-day)
- `/admin/scheduling/conflicts` — Conflict dashboard
- `/admin/dashboard` — Real-time status dashboard

**Automations:**

- Conflict detection (overlapping sessions, double-booked resources)
- Auto-suggest alternative times/rooms
- Generate personalized partner agendas
- Status tracking: `Pending → Scheduled → Confirmed`

---

### Phase 7: Partner Review & Change Requests

**Backend:**

- `ChangeRequestController` — create, approve, reject requests
- Auto-routing to appropriate internal teams

**Frontend Pages:**

- `/partner/schedule` — View assigned schedule and room
- `/partner/change-request` — Submit change request form
- `/admin/change-requests` — Manage incoming requests

**Automations:**

- Route requests to GHS (Programme), PCO (Logistics), Partnerships
- Track request status: `Pending → Approved → Rejected`
- Auto-update agendas and dashboards on approval
- Full audit log of all changes

---

### Phase 8: Finalization

**Backend:**

- `FinalizationController` — lock all sessions and data
- `PublishController` — publish agenda to website/app

**Frontend Pages:**

- `/admin/finalization` — Pre-lock checklist and publish controls
- `/admin/agenda/published` — Published agenda preview

**Automations:**

- Lock all sessions and partner data
- Publish finalized agenda
- Update management dashboards (daily schedule, rooms, resources)
- Status → `finalized`

---

### Phase 9: Conference Execution & Post-Event

**Backend:**

- `LiveTrackingController` — real-time session status
- `FeedbackController` — survey management and responses
- `ReportController` — analytics and report generation

**Frontend Pages:**

- `/admin/live` — Live conference dashboard
- `/partner/feedback` — Feedback survey form
- `/admin/reports` — Post-event analytics and reports

**Automations:**

- Real-time session status tracking
- Automated feedback surveys to partners post-event
- Generate analytics reports:
    - Sessions delivered count
    - Partner satisfaction scores
    - Resource utilization
    - Conflict resolution log

---

## 6. API Structure

```
# Public
GET    /api/conferences/{conference}/packages

# Partner Auth
POST   /api/partner/expression-of-interest
GET    /api/partner/dashboard
PUT    /api/partner/commitment
GET    /api/partner/agreement
POST   /api/partner/agreement/upload
GET    /api/partner/invoices
POST   /api/partner/payment
GET    /api/partner/onboarding
PUT    /api/partner/onboarding/{section}
POST   /api/partner/sessions
PUT    /api/partner/sessions/{session}
POST   /api/partner/submit
GET    /api/partner/schedule
POST   /api/partner/change-requests
GET    /api/partner/change-requests
POST   /api/partner/feedback

# Admin / Internal
GET    /api/admin/partners
GET    /api/admin/partners/{partner}
PUT    /api/admin/partners/{partner}/status
GET    /api/admin/finance/payments
PUT    /api/admin/finance/payments/{payment}/confirm
GET    /api/admin/scheduling/sessions
POST   /api/admin/scheduling/sessions/{session}/assign
GET    /api/admin/scheduling/rooms
POST   /api/admin/scheduling/rooms/{room}/assign
GET    /api/admin/scheduling/conflicts
POST   /api/admin/scheduling/resources
GET    /api/admin/agenda
POST   /api/admin/agenda/publish
GET    /api/admin/change-requests
PUT    /api/admin/change-requests/{id}/resolve
GET    /api/admin/reports
POST   /api/admin/finalize
GET    /api/admin/live-dashboard
```

> Note: Since Inertia.js is used, most routes serve Inertia pages (not pure JSON APIs).
> The routes above represent the controller actions — some return Inertia responses,
> others handle form submissions via Inertia's `router.post/put/delete`.

---

## 7. Key Services & Jobs

| Service/Job                     | Purpose                                             |
| ------------------------------- | --------------------------------------------------- |
| `AgreementGeneratorService`     | Generate pre-filled partnership agreement PDFs      |
| `InvoiceGeneratorService`       | Generate invoices with financial details            |
| `ConflictDetectionService`      | Detect scheduling overlaps and suggest alternatives |
| `AgendaGeneratorService`        | Build master and partner-specific agendas           |
| `OnboardingProgressService`     | Calculate section completion percentages            |
| `NotificationService`           | Centralized email/portal notification dispatch      |
| `SendPartnerNotification` (Job) | Queued email delivery                               |
| `GeneratePostEventReport` (Job) | Async report generation                             |
| `LockSubmissionsJob`            | Scheduled task to lock inputs before conference     |
| `SendFeedbackSurveys` (Job)     | Post-conference survey dispatch                     |

---

## 8. Middleware & Guards

| Middleware               | Purpose                                                  |
| ------------------------ | -------------------------------------------------------- |
| `EnsurePartnerConfirmed` | Block onboarding until payment confirmed                 |
| `EnsureNotLocked`        | Prevent edits after submission lock                      |
| `RoleMiddleware`         | Role-based access (admin, finance, programme, pco, etc.) |
| `TrackPartnerActivity`   | Log partner portal interactions                          |

---

## 9. Notifications & Emails

| Trigger                     | Recipients             | Channel        |
| --------------------------- | ---------------------- | -------------- |
| EOI Submitted               | AHAIC central, Partner | Email + Portal |
| Agreement Generated         | Partner                | Email          |
| Invoice Sent                | Partner                | Email          |
| Payment Confirmed           | Partner, Finance       | Email + Portal |
| Onboarding Section Complete | Internal teams         | Portal         |
| Submission Locked           | Partner                | Email + Portal |
| Session Scheduled           | Partner                | Email + Portal |
| Change Request Filed        | Internal teams         | Portal         |
| Change Request Resolved     | Partner                | Email + Portal |
| Agenda Published            | All partners           | Email          |
| Feedback Survey             | Partners               | Email          |

---

## 10. File Structure (New Files)

```
app/
├── Enums/
│   ├── PartnerStatus.php
│   ├── PaymentStatus.php
│   ├── InvoiceStatus.php
│   ├── SessionStatus.php
│   ├── SessionFormat.php
│   ├── ChangeRequestStatus.php
│   ├── ChangeRequestType.php
│   ├── UserRole.php
│   └── PackageTier.php
├── Http/
│   ├── Controllers/
│   │   ├── Partner/
│   │   │   ├── DashboardController.php
│   │   │   ├── ExpressionOfInterestController.php
│   │   │   ├── CommitmentController.php
│   │   │   ├── AgreementController.php
│   │   │   ├── InvoiceController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── OnboardingController.php
│   │   │   ├── SessionController.php
│   │   │   ├── SubmissionController.php
│   │   │   ├── ScheduleController.php
│   │   │   ├── ChangeRequestController.php
│   │   │   └── FeedbackController.php
│   │   └── Admin/
│   │       ├── DashboardController.php
│   │       ├── PartnerManagementController.php
│   │       ├── FinanceController.php
│   │       ├── SchedulingController.php
│   │       ├── RoomController.php
│   │       ├── ResourceController.php
│   │       ├── AgendaController.php
│   │       ├── ChangeRequestController.php
│   │       ├── ConferenceController.php
│   │       ├── FinalizationController.php
│   │       ├── LiveDashboardController.php
│   │       └── ReportController.php
│   ├── Middleware/
│   │   ├── RoleMiddleware.php
│   │   ├── EnsurePartnerConfirmed.php
│   │   ├── EnsureNotLocked.php
│   │   └── TrackPartnerActivity.php
│   └── Requests/
│       ├── Partner/
│       │   ├── ExpressionOfInterestRequest.php
│       │   ├── CommitmentRequest.php
│       │   ├── OnboardingRequest.php
│       │   ├── SessionRequest.php
│       │   └── ChangeRequestRequest.php
│       └── Admin/
│           ├── ScheduleSessionRequest.php
│           ├── RoomAssignmentRequest.php
│           ├── ResourceAssignmentRequest.php
│           └── PaymentConfirmationRequest.php
├── Models/
│   ├── Conference.php
│   ├── SponsorshipPackage.php
│   ├── Partner.php
│   ├── Agreement.php
│   ├── Invoice.php
│   ├── Payment.php
│   ├── Session.php
│   ├── SessionContact.php
│   ├── PartnerContact.php
│   ├── Room.php
│   ├── TimeSlot.php
│   ├── SessionSchedule.php
│   ├── ResourceAssignment.php
│   ├── ChangeRequest.php
│   ├── AuditLog.php
│   ├── FeedbackSurvey.php
│   └── BrandingRequirement.php
├── Services/
│   ├── AgreementGeneratorService.php
│   ├── InvoiceGeneratorService.php
│   ├── ConflictDetectionService.php
│   ├── AgendaGeneratorService.php
│   └── OnboardingProgressService.php
├── Jobs/
│   ├── SendPartnerNotification.php
│   ├── GeneratePostEventReport.php
│   ├── LockSubmissionsJob.php
│   └── SendFeedbackSurveys.php
├── Notifications/
│   ├── EOISubmittedNotification.php
│   ├── AgreementReadyNotification.php
│   ├── InvoiceSentNotification.php
│   ├── PaymentConfirmedNotification.php
│   ├── SubmissionLockedNotification.php
│   ├── SessionScheduledNotification.php
│   ├── ChangeRequestNotification.php
│   └── FeedbackSurveyNotification.php
└── Observers/
    ├── PartnerObserver.php
    └── SessionObserver.php

resources/js/
├── pages/
│   ├── Packages/
│   │   ├── Index.vue
│   │   └── Show.vue
│   ├── Partner/
│   │   ├── Dashboard.vue
│   │   ├── ExpressionOfInterest.vue
│   │   ├── Commitment.vue
│   │   ├── Agreement.vue
│   │   ├── Invoices.vue
│   │   ├── Payment.vue
│   │   ├── Onboarding/
│   │   │   ├── Index.vue
│   │   │   ├── OrganizationProfile.vue
│   │   │   ├── SessionSubmission.vue
│   │   │   ├── CommunicationsBranding.vue
│   │   │   └── Contacts.vue
│   │   ├── Review.vue
│   │   ├── Schedule.vue
│   │   ├── ChangeRequests/
│   │   │   ├── Index.vue
│   │   │   └── Create.vue
│   │   └── Feedback.vue
│   └── Admin/
│       ├── Dashboard.vue
│       ├── Partners/
│       │   ├── Index.vue
│       │   └── Show.vue
│       ├── Finance/
│       │   └── Payments.vue
│       ├── Scheduling/
│       │   ├── Index.vue
│       │   ├── Rooms.vue
│       │   ├── Resources.vue
│       │   └── Conflicts.vue
│       ├── Agenda/
│       │   ├── Master.vue
│       │   └── Published.vue
│       ├── ChangeRequests/
│       │   └── Index.vue
│       ├── Conferences/
│       │   ├── Index.vue
│       │   └── Edit.vue
│       ├── Finalization.vue
│       ├── LiveDashboard.vue
│       └── Reports.vue
├── components/
│   ├── partner/
│   │   ├── StatusTracker.vue
│   │   ├── OnboardingWizard.vue
│   │   ├── OnboardingProgress.vue
│   │   ├── SessionForm.vue
│   │   └── PackageCard.vue
│   ├── admin/
│   │   ├── SchedulingBoard.vue
│   │   ├── RoomGrid.vue
│   │   ├── ConflictAlert.vue
│   │   ├── AgendaTimeline.vue
│   │   ├── StatsCard.vue
│   │   └── PartnerStatusBadge.vue
│   └── shared/
│       ├── FileUpload.vue
│       ├── StatusBadge.vue
│       ├── DataTable.vue
│       └── ConfirmDialog.vue
├── composables/
│   ├── usePartnerStatus.ts
│   ├── useOnboarding.ts
│   └── useScheduling.ts
├── types/
│   ├── partner.ts
│   ├── conference.ts
│   ├── scheduling.ts
│   └── admin.ts
└── layouts/
    ├── PartnerLayout.vue
    └── AdminLayout.vue
```

---

## 11. Implementation Order

### Sprint 1: Foundation

1. Database migrations (all tables)
2. Eloquent models with relationships
3. Enums for status fields
4. Brand theme (colors, fonts) applied to Tailwind
5. Role-based middleware
6. Admin & Partner layout components

### Sprint 2: Partner Journey (Phases 0–3)

1. Sponsorship packages CRUD (admin)
2. Public packages page
3. Expression of Interest flow
4. Agreement generation (PDF)
5. Invoice generation (PDF)
6. Payment tracking & confirmation
7. Email notifications for each step

### Sprint 3: Onboarding (Phases 4–5)

1. Multi-step onboarding wizard
2. Organization profile form
3. Session submission form
4. Branding & communications form
5. Contacts management
6. Progress tracking
7. Final submission & lock

### Sprint 4: Internal Scheduling (Phase 6)

1. Room management
2. Time slot management
3. Session scheduling board
4. Resource assignment
5. Conflict detection engine
6. Master agenda generation
7. Real-time dashboard

### Sprint 5: Change Requests & Finalization (Phases 7–8)

1. Partner schedule view
2. Change request workflow
3. Approval/rejection flow
4. Finalization & locking
5. Agenda publishing

### Sprint 6: Conference & Post-Event (Phase 9)

1. Live tracking dashboard
2. Feedback surveys
3. Analytics & reporting
4. Post-event report generation

---

## 12. Security Considerations

- RBAC enforced at middleware and policy levels
- All file uploads validated (type, size) and stored outside public root
- CSRF protection on all forms (Inertia handles this)
- Rate limiting on auth and sensitive endpoints
- Audit logging for all state changes
- Input sanitization for all user-provided content
- Signed URLs for agreement/invoice downloads
- 2FA available for admin users (already in skeleton)

---

## 13. Testing Strategy

- **Unit Tests:** Services (conflict detection, PDF generation, progress calculation)
- **Feature Tests:** Full HTTP lifecycle for each phase
- **Browser Tests:** Critical partner journey flows (optional, with Laravel Dusk)
- **Pest** framework (already configured in skeleton)

---

_Document created: 2026-04-01_
_Last updated: 2026-04-01_
