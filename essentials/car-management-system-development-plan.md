# CAR Management System — Development Plan

**Stack baseline:** Laravel 13 (PHP 8.4) · Livewire *(to install in Phase 0)* · Tailwind 4 · Pest 5 · Pint · SQLite (local) → MySQL (production) · Vite 8
**References:** `CAR_DIGITALIZATION REQUEST REQUIREMENT 8.19.26.pdf` (behavior spec — 13 steps, flowchart, Table Egg / DOP matrices) · `car-management-system-mockup.html` (UI contract — every view in it becomes a real route; anything not in it is listed as "not in mockup" in §4) · `CAR_rolly funa_1.pdf`, `CAR_ELMER GALLARDO_2_1.pdf`, `CAR_JUN QUITEVIS 9-12-26 1.0.pdf` (real sample CARs — used as seed data and as the UAT script) · `development-playbook.md` (how we work through the stages)

> **Status update (Oct 5, 2026):** the UI-scaffold pass (Playbook, Stage 2) and **Phases 0, 1 and 2 are complete**. **Next: Phase 3** (Phase I screens on real data: New CAR, release / reject, CAR list and detail).
>
> - **Stage 1 (UI concept):** `car-management-system-mockup.html` — six roles, full Phase I → III workflow, role-gated actions, Admin screens, sample data. It remains the UI contract and design-token source.
> - **Phase 0 (foundation + scaffold port):**
>   - Livewire 4.4.7 and Laravel Boost (dev) are installed.
>   - The mockup's CSS is ported verbatim into `resources/css/app.css`.
>   - The sidebar layout and role-aware navigation are in place.
>   - Sign-in has no self-registration and a local-only role switcher. Deactivated users are signed out.
>   - `Role` enum and area gates exist, and the access-log listener records login, logout and failed sign-ins.
>   - Every §4 route renders a Livewire component on the mockup's markup.
>   - The seeder creates one account per role, named after the people on the sample forms.
>   - 71 Pest tests pass.
> - **Still fake:** CAR data and workflow buttons come from `App\Services\ScaffoldData` (a port of the mockup's sample data). Buttons are authorized stubs that say which phase wires them up. Each later phase deletes the part of `ScaffoldData` it replaces.
>
> **Deviations during Phase 0:**
>
> - *(actual: users carry `role` + a free-text `scope` column (e.g. `PFC`, `Sales`) instead of `farm_id`; Phase 1 replaces `scope` with the `farms` FK once that table exists.)*
> - *(actual: `approver_id` is already on `users` and seeded — Gab and Alvin → Stephanie, Roi → Reneliza — so the Step 10 approver chain can be tested from Phase 1 on.)*
> - *(actual: area access uses gates (`view-dashboard`, `create-cars`, `view-queue`, `view-overdue`, `administer`) — per-CAR rules move to `CarPolicy` in Phase 2.)*
> - *(actual: Users & Roles lists real accounts; only its add/edit/deactivate buttons are stubs.)*
> - *(actual: `APP_NAME` is "CAR Management System"; Boost regenerated `CLAUDE.md` / `AGENTS.md` with project guidelines.)*
>
> **Phase 1 (reference data) — done:**
>
> - The reference data now lives in the database:
>   - farms, business lines and Issued To units;
>   - categories with response and implementation days;
>   - sub-categories with the matrix's "what to report" text.
> - `ReferenceDataSeeder` is safe to re-run in production: it creates missing rows and never overwrites days the Admin has edited.
> - **Users & Roles** works end to end: create, edit, reset password, deactivate and reactivate, with the approver chain enforced.
> - **Category Matrix** saves the day values.
> - The New CAR form reads units, categories and sub-categories from the database and shows each sub-category's guidance.
> - 124 Pest tests pass.
>
> **Deviations during Phase 1:**
>
> - *(actual: users carry a nullable `farm_id`, required only for Responder / Responder Approver. The other roles have no scope column; the UI shows "Issuing department", "All farms" or "IT" instead.)*
> - *(actual: approver chain rules.)*
>   - The approver must be active, must not be the user themselves, and must not lead back to the user through the chain.
>   - The approver's role is fixed per role:
>     - Requestor and Requestor Approver → a Requestor Approver;
>     - Responder and Responder Approver → a Responder Approver on the same farm;
>     - Monitor and Admin have none.
>   - "An approver of the same kind" is a provisional answer to §7 #1. The approver is still optional per user.
> - *(actual: admin safeguards.)* An admin cannot change their own role or deactivate themselves. A user that active people report to cannot be deactivated or change role until those people are reassigned.
> - *(actual: the Admin types an initial password and shares it directly. An emailed invite waits for mail in Phase 6.)*
> - *(actual: admin screens are protected by the `administer` gate rather than `UserPolicy` / `CategoryPolicy` — every action there is admin-only. Per-record policies start with `CarPolicy` in Phase 2.)*
> - *(actual: the matrix screen edits days only — 1–60 response, 1–90 implementation, implementation ≥ response. Adding or renaming categories or sub-categories is not built.)*
> - *(actual: an audit trail of admin changes is not built yet — still planned with the audit work in Phase 9.)*
> - *(actual: Issued To units are not linked to farms — §7 #7 is still open.)*
> - *(actual: the sample CARs in `ScaffoldData` keep a frozen copy of the original timelines, the way real CARs will snapshot theirs.)*
>
> **Phase 2 (core domain + state machine) — done (Oct 5, 2026):**
>
> - New tables: `cars`, `car_rounds`, `car_events` (append-only, enforced in the model) and `car_sequences`.
> - New enums: `CarStatus`, `CarAction`, `ComplaintType`.
> - Services:
>   - `CarNumberGenerator` hands out `CAR-YYYY-NNNN` per year, with the sequence row locked.
>   - `DeadlineCalculator` computes calendar-day deadlines.
>   - `CarWorkflow` holds the transition table plus `submit`, `apply`, `can` and `availableActions`. Every move:
>     - runs in a transaction with the CAR row locked;
>     - re-checks the status under that lock;
>     - writes a history row;
>     - fires `CarTransitioned` after commit.
> - `CarPolicy` exists. The Phase I lock is enforced in the model.
> - `SampleCarSeeder` replays the mockup's ten CARs through the real workflow on their original dates. They keep references CAR-2026-0138 … 0147, and the next new CAR is 0148.
> - 225 tests pass, including a role × status × action matrix written independently of the code.
> - **The screens still read `ScaffoldData`; Phase 3 switches them to the database.**
>
> **Deviations during Phase 2:**
>
> - *(actual: there is no separate `CarEventType` — history rows are typed by `CarAction`, including `submit`.)*
> - *(actual: added a `Voided` status. The IT Admin can void any open CAR, and must give a reason.)*
> - *(actual: rounds.)* Only "not effective" and "not accepted" open a new round. "Return for revision" (Step 10) stays in the same round, with its reason in the history.
> - *(actual: required inputs.)* Reject, return, not effective and void need a reason. "Not accepted" needs a new end date after today.
> - *(actual: who may act.)*
>   - Anyone holding the owning role may act on a CAR — for Responder roles, on the CAR's farm.
>   - Only the Requestor who filed a CAR may resubmit it.
>   - The approver chain does not restrict routing yet; Step 10 escalation is still §7 #1.
> - *(actual: `complaint_type` and `complaint_received_on` are on `cars`; the form fields arrive in Phase 3.)*
> - *(actual: `corrective_actions`, `verifications` and `attachments` are not created yet; they come with the Phase 4–5 forms.)*
> - *(actual: assumptions now in code.)* Deadlines count calendar days (§7 #4), and every active user can view every CAR (§7 #5).
>
> **Deviations from the requirements document made during the scaffold** — log further ones here as *(actual: …)*:
>
> - *(actual: roles are named by function — Requestor, Requestor Approver, Responder, Responder Approver, Monitor, IT Admin — instead of Supervisor / Manager / Division Head, which were confusing across farms.)*
> - *(actual: added a **release approval** before submit — "Awaiting Release" — from the flowchart's "approval of immediate manager → Submit CAR / Reject CAR" and the samples' "Noted by". The requirements' numbered steps omit it.)*
> - *(actual: the "Manager prepares directly → DH approves" path of Step 10 is **dropped** from the mockup, pending the approver-chain decision in §7.)*
> - *(actual: statuses renamed to follow the roles, e.g. "Awaiting Responder Approval", "Awaiting Effectiveness Check".)*
> - *(actual: "Open — Not Accepted" now has an owner (Responder) and a revised end date, so Step 13's loop back to Step 11 is reachable.)*
> - *(actual: IT Admin added; read-only on CARs, owns users, roles and the category matrix.)*

---

## 1. Recommended tech per function

| Function | Default recommendation | Alternative / notes |
|---|---|---|
| Auth | Laravel session auth with a Livewire login page. **No self-registration** — IT Admin creates users. Dev-only role switcher (local env) replaces the mockup's "pick any role" login. | Company SSO / LDAP later; the `User` model stays the same. |
| Roles & permissions | One `role` enum column on `users` (6 values) + a farm/unit scope column + `approver_id` (self-reference). Enforced in **policies**. | `spatie/laravel-permission` only if a person must hold several roles at once. Not needed for v1. |
| Core domain state machine | `CarStatus` enum + a `CarWorkflow` service holding one transition table (from-status, action, allowed role, to-status, side effects). Every button calls the service. | `spatie/laravel-model-states` if the table outgrows a plain class. |
| Notifications | Laravel database notifications for the in-app flag (bell + "My Queue" count), queued mail on top. Scheduled job for due-soon / overdue reminders. | Viber/SMS or Teams webhook later — same notification classes, new channel. |
| File attachments | Polymorphic `attachments` table, files on the private `local` disk, authorized download route (no public URLs). Accept images, **video (mp4/mov)**, PDF, Office docs, screenshots. | S3/MinIO if storage grows. Raise PHP/web-server upload limits — the samples contain videos. |
| Reference numbers | `CAR-{year}-{0000}` from a `car_sequences` row locked inside the create transaction (no duplicates under concurrency). | DB sequence on MySQL 8+ — not worth the portability loss with SQLite in dev. |
| Deadline calculation | A `DeadlineCalculator` service: issued date + matrix days, **snapshotted onto the CAR at creation**. | See §7 — calendar vs business days is undecided. |
| Backups + health check | `spatie/laravel-backup` nightly (DB + attachments), plus Laravel's `/up` endpoint and a scheduled backup-age check. | Server-level snapshots if IT already runs them. |
| Import/export | (a) printable CAR form matching the paper layout (Parts I–VI) as PDF; (b) list/dashboard export to Excel/CSV; (c) admin CSV import for historical CARs with a preview step. | Browser print stylesheet first; PDF library only if print styling is not good enough. |
| Dashboard | Server-side SQL aggregates (counts by status, category/sub-category, month; average response and resolution time). Charts in Chart.js or plain CSS bars like the mockup. | A materialized summary table only if volume demands it. |
| Audit trail | Two layers: (1) `car_events` — append-only per-CAR timeline (this is the mockup's "History" panel); (2) model auditing for admin-owned data (users, roles, matrix). | `owen-it/laravel-auditing` for layer 2. |
| Access log | Listener on Login / Logout / Failed events, plus a log line on every attachment download and CAR print. | — |
| Sensitive fields | Nothing reversible-sensitive in v1. If customer phone/e-mail is added to the complainant, store it with Laravel's `encrypted` cast. | — |
| Danger zone (destructive ops) | **No hard delete of CARs.** Admin can *void* a CAR with a reason (kept in `car_events`). Matrix edits only affect CARs issued after the edit (deadlines are snapshotted). | A purge job for old attachments, preview → confirm → run. |
| Queue driver | `database` (already configured). Scheduler via cron / Windows Task Scheduler. | Redis if notification volume grows. |
| Testing | Pest (installed): one feature test per workflow transition, a role × action policy matrix, a deadline-calculator unit test, and the three sample CARs replayed end to end. | Browser tests (Pest browser) for the critical Phase I → closure path once stable. |

---

## 2. Folder structure

```
app/
├── Enums/             # Role, CarStatus, CarPhase, ComplaintType, CarEventType, AttachmentKind
├── Models/            # User, Farm, BusinessLine, IssuedToUnit, Category, Subcategory,
│                      # Car, CarRound, CorrectiveAction, Verification, Attachment, CarEvent
├── Livewire/          # one folder per sidebar item (see §4)
│   ├── Dashboard/
│   ├── Cars/          # Index, Create, Show (+ phase partials)
│   └── Admin/         # Users, Matrix, Import, Audit
├── Services/          # CarWorkflow (state machine), DeadlineCalculator, CarNumberGenerator,
│                      # DashboardMetrics, CarImporter
├── Policies/          # CarPolicy, UserPolicy, CategoryPolicy, AttachmentPolicy
├── Jobs/              # SendDeadlineReminders, BackupHealthCheck, ImportHistoricalCars
├── Observers/         # audit hooks for User / Category / Subcategory
├── Notifications/     # CarAssigned, CarReturned, CarNotAccepted, DeadlineApproaching, CarOverdue
└── Listeners/         # access-log listeners

resources/views/…      # Blade layout (sidebar shell from the mockup) + Livewire views
routes/web.php         # thin: route → Livewire component, all inside auth middleware
database/seeders/      # reference data from the matrix PDF + the mockup's sample CARs
```

**Conventions to commit to early**

- Authorization happens in **policies**, called from every entry point (Livewire action, route, download, print) — never only from sidebar visibility. The mockup hides buttons by role; the real app must also *refuse* the action.
- **One transition table** (`CarWorkflow`) is the only code allowed to change `cars.status`. Components never assign status directly.
- Every table/list uses the mockup's action grammar: click a row → detail; the action bar on the detail page offers only the buttons the current role may press for the current status.
- Tailwind theme tokens are lifted from the mockup's `:root` variables (including the dark-mode set) so the port looks the same.

---

## 3. Data model — migrations & relationships

### Migration order (respects FK dependencies)

```
1.  farms                    (PFC, HATCHERY, BROOKDALE, RH/BBGC, BFC)
2.  business_lines           (TABLE EGG, DOP)
3.  issued_to_units          (business_line_id, name — Eggroom, Egg Grading, PFC Sales, …)
4.  categories               (business_line_id, name, response_days, implementation_days)
5.  subcategories            (category_id, name, description  ← the matrix's "what to report")
6.  users                    (role, farm_id/scope, approver_id → users, active)
7.  car_sequences            (year, last_number)
8.  cars                     (see below)
9.  car_rounds               (car_id, round_no — one per Phase II/III attempt)
10. corrective_actions       (car_round_id, description, responsible_id, starts_on, ends_on)
11. verifications            (car_round_id — effectiveness check + final acceptance)
12. attachments              (morph: attachable_type/id, kind, path, mime, size, uploaded_by)
13. car_events               (car_id, type, actor_id, from_status, to_status, note, created_at — append-only)
14. notifications, jobs, cache  (Laravel defaults)
15. access_logs, audits      (hardening phase)
```

### Relationship map

```
BusinessLine 1─* IssuedToUnit
BusinessLine 1─* Category 1─* Subcategory

Farm 1─* User ─── approver ──> User          (chain used by Step 10 routing)

Car *─1 IssuedToUnit, *─1 Category, *─1 Subcategory, *─1 Farm
Car *─1 User (requestor)   *─1 User (release approver)
Car 1─* CarRound 1─* CorrectiveAction
CarRound 1─* Verification
Car / CarRound / CorrectiveAction / Verification 1─* Attachment   (polymorphic)
Car 1─* CarEvent
```

### Modeling decisions worth locking in

- **One status enum, not flags.** `cars.status` holds the single current state; "who owns it now" is derived from status + farm (as `ownerOf()` does in the mockup) and cached in `current_owner_role` for fast queue queries.
- **Rounds, not overwritten fields.** Step 12 "not effective" and Step 13 "not accepted" send a CAR back through Phase II/III. Each pass is a new `car_rounds` row, so history, the samples' "1st / 2nd verification" boxes, and response/resolution metrics stay accurate.
- **Deadlines are snapshotted.** `response_due_on` and `implementation_due_on` are written when the CAR is created. A revised date after "not accepted" goes in `revised_due_on` and takes over for the overdue check. Editing the matrix never moves existing deadlines.
- **Three dates per CAR, not one.** `complaint_received_on` (when the customer complained), `issued_on` (auto, today), and `created_at`. The samples show gaps of 2–15 days between them, and one has a wrong year (2025 vs 2026), so system-generated issue dates fix that class of error.
- **Type of complaint** (`product`, `service`, `other`) and **assigned agent** are columns on `cars` — both are on every sample form but were missing from the requirements.
- **Phase I lock.** After release, Phase I columns are immutable. Enforce in the policy **and** in the model (`updating` guard), not just by disabling inputs.
- **Scope guard.** A Responder / Responder Approver may act only on CARs for their farm. Enforce in the policy **and** in the "My Queue" query.
- **Repeat offense** = CARs sharing the same sub-category and issued-to unit within a rolling window (default 90 days, adjustable by Admin). Computed in `DashboardMetrics`, never stored.
- **Visibility.** Entry points that must all apply the same rule: list, direct `/cars/{car}` URL, attachment download, print/PDF, export, notification links. Current assumption: all roles may *see* all CARs (the mockup's "All CARs"), only the owner may *act* — confirm in §7.

---

## 4. UI module ↔ mockup mapping

| Mockup view (static) | Real route | Component | Roles |
|---|---|---|---|
| Login (pick a role) | `/login` (+ dev-only role switcher) | `Auth\Login` | all |
| Dashboard | `/dashboard` | `Dashboard\Index` | Monitor (optionally approvers) |
| List — My Queue / My Approvals | `/cars?view=mine` | `Cars\Index` | Requestor, Requestor Approver, Responder, Responder Approver |
| List — All CARs | `/cars` | `Cars\Index` | all |
| List — Overdue | `/cars?view=overdue` | `Cars\Index` | Responder Approver, Monitor, IT Admin |
| CAR detail + action bar + history | `/cars/{car}` | `Cars\Show` | all (actions role-gated) |
| New CAR (Phase I) | `/cars/create` | `Cars\Create` | Requestor |
| Users & Roles | `/admin/users` | `Admin\Users` | IT Admin |
| Category Matrix | `/admin/matrix` | `Admin\Matrix` | IT Admin |

**Not in the mockup yet (add in the phase shown):**

| Item | Route | Phase |
|---|---|---|
| Phase II response form (containment, root cause, corrective-action lines) | inside `Cars\Show` | 4 |
| Evidence upload and verification forms | inside `Cars\Show` | 5 |
| Notification bell + inbox | layout | 6 |
| Printable CAR form (Parts I–VI) | `/cars/{car}/print` | 8 |
| Historical import | `/admin/import` | 8 |
| Audit viewer, backup status | `/admin/audit`, `/admin/system` | 9 |
| Type of complaint / complaint-received date on the New CAR form | `Cars\Create` | 3 |

---

## 5. Build order

Each phase ends runnable and demoable. Don't start a phase before the previous one's tests pass.

> **Note (post-scaffold):** "build X module" means: add the migrations/models/policies, then swap the component's hardcoded data for real queries and its stubbed actions for real ones — keeping the mockup's markup, and its sample data as seeders, intact.

### Phase 0 — Foundation *(everything depends on this)*
- Install Livewire; set up Tailwind tokens from the mockup; run Pint and Pest in CI-style scripts.
- App layout: sidebar shell, role-aware nav, topbar (ported from the mockup).
- Auth (no self-registration), `Role` enum, role gates, dev-only role switcher, access-log listener.
- **Scaffold port:** every §4 route renders a Livewire component with the mockup's markup and hardcoded sample data. This is the "UI scaffold" and must be demoable to stakeholders before any domain code.

### Phase 1 — Reference data
- Migrations and seeders for farms, business lines, issued-to units, categories, sub-categories, built **directly from the requirements PDF's two matrices** (response 3 / 10 for Production Related; 1 / 5 for most others; DOP Compliance 1 / 3).
- Admin CRUD: users & roles (incl. approver chain), category matrix. Replace the scaffold data on those two screens.

### Phase 2 — Core domain + state machine
- `cars`, `car_rounds`, `car_events`, `car_sequences`; `CarStatus`, `CarEventType`.
- `CarWorkflow` with the **full transition table** and Pest tests for every transition and every disallowed one, **before any UI touches it**.
- `DeadlineCalculator`, `CarNumberGenerator` with tests.

### Phase 3 — Phase I: initiation & release
- `Cars\Create`: cascading Issued To → Category → Sub-category, live deadline preview, complaint type and received date, attachments, validation.
- Requestor Approver release / reject; rejected CARs return to the Requestor; Phase I lock on release.
- Replaces the scaffold's New CAR and the Phase I card on the detail page.

### Phase 4 — Phase II: response & approval
- Interim containment (dates, responsible), root cause (text or file), corrective-action lines (start = containment end, end = implementation deadline).
- Responder Approver approve / return, with a required reason on return.

### Phase 5 — Phase III: implementation, verification, closure
- Evidence upload (date, responsible); effectiveness check; final acceptance by the Requestor Approver.
- "Not accepted" → revised end date, owner flagged, loop to evidence upload. Each loop opens a new `car_round`.

### Phase 6 — Notifications & reminders
- Notification classes per transition, in-app bell, queued mail.
- Scheduled due-soon (e.g. 1 day before) and overdue reminders; overdue uses `revised_due_on` when present.

### Phase 7 — Dashboard & reporting
- `DashboardMetrics`: open/closed, overdue, **response time** (response submitted − issued), **resolution time** (closed − issued), **frequency of issuance** (by month, unit, agent), **repeat offenses** by category and sub-category.
- Replace every hardcoded number in the mockup's dashboard. Filters: date range, business line, farm.

### Phase 8 — Documents & import
- Printable CAR form laid out like the paper form (Parts I–VI, prepared / noted / acknowledged blocks), PDF or print stylesheet.
- List and dashboard export.
- Historical CSV import with a preview and a "dry run" report, so existing paper CARs can be loaded with their original dates.

### Phase 9 — Maintenance
- Backups + backup-age health check, audit viewer, void-CAR flow (reason required), attachment purge job (preview → confirm → execute).

### Phase 10 — Hardening *(checklist-driven)*
- [ ] Every route/action authorized by policy, not menu visibility.
- [ ] No CAR reachable by direct link, download, print or export that its list would hide.
- [ ] Every workflow transition has a reachable control **and** a real handler.
- [ ] Test coverage: full happy path + every branch (reject at release, return at Step 10, not effective at Step 12, not accepted at Step 13) + every role × status permission combination.
- [ ] Phase I fields cannot change after release (tested at model level).
- [ ] Deadlines do not move when the matrix is edited.
- [ ] Attachments are only served through the authorized route.
- [ ] **UAT:** the three sample CARs (Funa, Gallardo, Quitevis) replayed end to end by real users in each role.

---

## 6. Suggested first week

1. Phase 0 in full — including the scaffold port, so stakeholders see the real app shell by the end of the week.
2. Phase 1 migrations + seeders from the matrix PDF, mirroring the mockup's sample data, then the Users and Matrix screens.
3. Write the `CarWorkflow` transition table **on paper first** (status × action × role → next status), review it with the process owner, then implement it as tested code in Phase 2.

---

## 7. Open decisions *(resolve before the phase shown)*

| # | Question | Needed by | Current assumption |
|---|---|---|---|
| 1 | **Step 10 routing** — approver chain with an escalation approver, or a third approver role, when an approver prepares the response directly? | Phase 1 (users table) | Approver chain via `users.approver_id`. |
| 2 | **Who picks the category/sub-category?** Spoilage from 3-day-old eggs fits both Production Related → Internal Quality (3 / 10 days) and Compliance → Storage & Handling (1 / 5 days). | Phase 3 | Requestor picks; Requestor Approver can correct it at release. |
| 3 | **Who writes the interim containment?** The requirements put it in Phase II (Responder); on all three samples the issuing agent wrote it. | Phase 4 | Responder writes it; the Requestor's initial customer contact goes in the problem details. |
| 4 | **Calendar days or business days** for the matrix timelines? | Phase 2 | Calendar days. |
| 5 | **Visibility** — can every role see every CAR, or only their own farm / department? | Phase 2 (policies) | Everyone sees all; only the owner acts. |
| 6 | **Repeat-offense window** and key (sub-category only, or sub-category + unit)? | Phase 7 | 90 days, sub-category + unit. |
| 7 | **Issued To vs Farm** — the requirements have one dropdown; the mockup has Farm and Unit. Is the responding farm derivable from the unit? | Phase 1 | Keep both until confirmed. |
| 8 | **Multiple Issued To** — the flowchart says a CAR "can be issued multiple". One CAR to several units, or one CAR per unit? | Phase 2 | One CAR per unit, created together from one form. |
| 9 | **Historical CARs** — import the paper CARs already issued? With which dates? | Phase 8 | Yes, via CSV import with the original issued dates. |
