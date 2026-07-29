# AHAIC Partner Portal — Implementation Status

_Last reviewed: 2026-06-25_

This document tracks what has shipped against the [Implementation Plan](IMPLEMENTATION_PLAN.md) and the **AHAIC Partner Portal Engagement Process** brief. It also captures the new session-slot scheduling additions sourced from `Session Slots & Booths Scheduling Matrix.xlsx`.

---

## 1. Done — by phase

### Phase 0 / 1 — Discovery & Expression of Interest
- Public package browsing pages (`/packages`, `/packages/{slug}`)
- EOI form with **save draft → submit → reopen-after-rejection** flow
- Auto status: `interest_submitted`; email to AHAIC central
- Backend feature tests + frontend workflow-state tests passing

### Phase 2 — Package Commitment, Agreement & Invoice
- Package confirmation (billing / tax details)
- `AgreementGeneratorService` (DomPDF) → download, digital-sign, upload-signed
- `InvoiceGeneratorService` → invoice number, customer code, benefits summary, bank details
- Email + portal notifications: agreement ready, invoice sent

### Phase 3 — Payment & Confirmation
- Partner payment-proof upload (with `supporting_document`)
- Finance dashboard: confirm / reject; status drives `confirmed` and unlocks onboarding
- Backfill migration `2026_05_07_120000_backfill_missing_agreement_and_invoice_workflow_columns` keeps existing rows healthy

### Phase 4 — Partner Onboarding
- Multi-step wizard: Organization profile, Communications & branding, Contacts, Sessions
- `OnboardingProgressService` recomputes per-section completion JSON on each save
- File uploads (logo, branding assets), Save & Resume at any step
- Middleware: `partner.confirmed`, `partner.unlocked`

### Phase 5 — Final Submission
- `/partner/review` and submit flow
- Lock inputs, transition sessions → `submitted`
- Routes notifications to Programme (GHS), PCO, Partnerships + AHAIC central

### Phase 6 — Internal Planning & Allocation (admin scheduling)
- Drag-and-drop **scheduling board** + room-allocation matrix view
- `ConflictDetectionService` — overlapping sessions, partner double-booking, room double-booking
- `RoomAllocationMatrixService` — daily matrix, slot load summary, room-utilisation stats, fit warnings (capacity + format suitability)
- `AgendaGeneratorService` — master and partner-specific agendas
- Resource assignment (rapporteurs, session leads, AV) tied to a schedule

### Cross-cutting
- RBAC enums (`super_admin`, `admin`, `finance`, `programme`, `pco`, `communications`, `partnerships`, `partner`) + `role:` middleware
- AHAIC brand theme (Land Green / Off White / Sunshine Yellow / Independence Red / Ocean Blue / Light Blue / Earth Brown, Ojuju headings + Arimo body)
- Audit log model + observer hooks
- Database notifications + portal status banners

---

## 2. New in this iteration — Session-Slot Picker (from the Matrix workbook)

The "Session Slots & Booths Scheduling Matrix" workbook has been turned into a normalised inventory so partners can self-serve a preferred slot during session submission.

### Data model
| Table | Purpose |
|---|---|
| `session_slots` | Bookable inventory: `slot_code`, `slot_category` (plenary/pop/breakfast/parallel/reception/other), `track_label` (e.g. *Parallel Track 1*, *BT 2*, *RT 1*), `day_index`, `date`, `time_label`, `start_time`/`end_time`, `default_room_id`, `default_format`, `is_assignable`, **`claimed_by_session_id`**, `claimed_at`. Unique on `(conference_id, slot_code)`. |
| `conference_sessions.session_slot_id` | FK back to the claimed slot. Nullable — programme team can still assign manually. |
| `booths` | Booth inventory normalised from §3 of the matrix: `zone` (Foyer 1A / 1B), `booth_number` (1–25 / 26–40), `size` (3×3), `status` (`available`/`reserved`/`assigned`/`blocked`), `partner_id`. |

### Seeded inventory (`SessionSlotSeeder`)
Mirrors the matrix's *Allocation matrix tables* tab:

| Day | Composition | Bookable | Locked (Amref-led) |
|---|---|---|---|
| **Day 0** | Youth Summit, Reception 0 | 1 reception | 1 (Youth Summit) |
| **Day 1** | 4 × Breakfast, Plenary 1, 6 × Parallel (Track 1), 6 × Parallel (Track 2), Plenary 2, POP 1, Reception 1 | 18 | 2 plenaries |
| **Day 2** | 4 × Breakfast, Plenary 3, 6 × Parallel (Track 3), 6 × Parallel (Track 4), Plenary 4, POP 2, Reception 2 | 18 | 2 plenaries |
| **Day 3** | 3 × Breakfast, 5 × Parallel (Track 5), 5 × Parallel (Track 6), POP 3, Plenary 5 | 14 | 1 plenary |
| **Booths** | Foyer 1A booths 1–25, Foyer 1B booths 26–40 | 40 | — |

Seed verified: `57 slots; 40 booths`.

### Partner UX
- `Partner/Sessions/Create.vue` and `Partner/Sessions/Edit.vue` now render a **Preferred Session Slot** card grouped by day, showing slot code, time, track, default room, and setup format.
- Selection is optional but recommended; clearing releases the slot.
- Submission uses `useForm({ session_slot_id })` so the slot id flows through the existing Inertia form.

### Atomic claim semantics (`Partner/SessionController`)
- `availableSlotsFor()` lists slots where `is_assignable && claimed_by_session_id IS NULL` (plus the partner's current pick on edit).
- `claimSlot()` runs inside a `DB::transaction` with `lockForUpdate()` and throws a `ValidationException` if another partner just took the slot — first-come, first-served.
- Updating a session that switches slot releases the old one and locks the new one in the same transaction.
- Deleting a draft session releases its slot.

---

## 3. Pending — from the Implementation Plan

### Phase 6 follow-up
- [ ] Admin **booth allocation matrix** UI (`booths` table now exists; needs assignment board: zone → booth grid with package/partner tags)
- [ ] Spreadsheet import / reconciliation flow — read an updated matrix workbook and reconcile against the inventory (currently seeded; need an upload + diff/apply tool)
- [ ] Programme-team override panel to mark partner-picked slots as confirmed vs. needs-relocation

### Phase 7 — Partner Review & Change Requests
- [ ] Frontend verification of change-request lifecycle (routes/controllers exist; needs feature tests + UI verification)

### Phase 8 — Finalization
- [ ] Pre-lock checklist UI + publish to external agenda surface
- [ ] Snapshot management dashboards

### Phase 9 — Live conference & post-event
- [ ] Live tracking dashboard wiring (Reverb/Pusher channel)
- [ ] Feedback survey delivery via `SendFeedbackSurveys`
- [ ] Analytics & post-event PDF report generation

### Public browsing
- [ ] Public package browsing (`/packages` shell exists but is not yet fully verified — no feature tests cover the anonymous flow)

---

## 4. How to exercise locally

```bash
# fresh schema + slot inventory
php artisan migrate:fresh --seed

# inspect the seeded slot inventory
php artisan tinker --execute="\App\Models\SessionSlot::with('defaultRoom')->get(['slot_code','slot_category','track_label','day_index','time_label','is_assignable'])->each(fn(\$s)=>print(\$s->slot_code.' | '.\$s->time_label.' | '.\$s->track_label.PHP_EOL));"

# log in as the seeded partner (partner@example.com) and open /partner/sessions/create
```

---

## 5. Files touched in this iteration

```
database/migrations/2026_06_25_000001_create_session_slots_table.php   (new)
database/migrations/2026_06_25_000002_create_booths_table.php          (new)
database/seeders/SessionSlotSeeder.php                                 (new)
database/seeders/DatabaseSeeder.php                                    (call SessionSlotSeeder)
app/Models/SessionSlot.php                                             (new)
app/Models/Booth.php                                                   (new)
app/Models/ConferenceSession.php                                       (session_slot_id + relation)
app/Http/Controllers/Partner/SessionController.php                     (slot listing + atomic claim/release)
resources/js/pages/Partner/Sessions/Create.vue                         (slot picker card)
resources/js/pages/Partner/Sessions/Edit.vue                           (slot picker card)
STATUS.md                                                              (this file)
```
