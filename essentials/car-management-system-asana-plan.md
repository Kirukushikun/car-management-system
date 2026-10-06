# CAR Management System — Development Plan

**Stack:** Laravel 13 (PHP 8.4) · Livewire 4 · Tailwind 4 · Pest 5 · SQLite (dev) → MySQL (internal server)
**Status:** Build complete — awaiting UAT and go-live
**Last updated:** 2026-10-06
**Repo:** local Git (`main`, no remote yet) · **Prod URL:** internal server (to be set up)

---

## Objectives

1. **Digitalize the CAR process** — replace the Word/PDF Corrective Action Request forms for TABLE EGG and DOP with one system that carries each CAR from issuance through response, implementation, verification and closure.
2. **Enforce timelines and accountability** — deadlines calculated automatically from the category matrix, every step routed to the right role and farm, and a complete history of who did what and when.
3. **Give management visibility** — a dashboard of repeat offenses by category and sub-category, open vs closed, response time, resolution time and frequency of CAR issuance.

---

## 1. Planning

| Status | Task | Notes |
|---|---|---|
| ✅ | Analyze requirements / problem statement | 8.19.26 requirement (13 steps, 3 phases, Table Egg & DOP matrices) read against 3 real CARs (Funa, Gallardo, Quitevis) |
| ✅ | Set up Git repo & local dev environment | Laravel 13 on Laragon, Livewire 4, Laravel Boost, SQLite; Git on `main` |
| ✅ | Define scope — what's in, what's explicitly out | In: TABLE EGG & DOP CARs, 6 roles, dashboard, admin. Out for v1: SSO, SMS/Viber, other business lines |

---

## 2. Design

| Status | Task | Notes |
|---|---|---|
| ✅ | Build static HTML/UI mockup | `car-management-system-mockup.html` — every screen, all roles, sample data; the UI contract |
| ✅ | Settle domain naming / glossary | Function-based roles (Requestor, Requestor Approver, Responder, Responder Approver, Monitor, IT Admin); 11 statuses; 13 actions |
| ✅ | Data model / database design | 17 tables built; verifications are read from the CAR history per round |

---

## 3. Build

### 3a. Recommended tech per function

| Function | Default | Alternative / notes |
|---|---|---|
| Auth | Laravel session auth, no self-registration — IT Admin creates accounts; local-only role switcher | Company SSO later |
| Roles & permissions | `Role` enum on users + farm link + approver chain; gates and `CarPolicy` | spatie/laravel-permission only if one person needs several roles |
| Core status flow | `CarStatus` enum + hand-written `CarWorkflow` transition table — the only code that changes status | spatie/laravel-model-states if it outgrows one class |
| Reference numbers | `CAR-YYYY-NNNN` from a locked per-year sequence | — |
| Deadlines | Issued date + matrix days, snapshotted on the CAR (calendar days) | Business-day calculator if decided |
| Notifications | Database notifications (in-app flag) + queued mail | Viber/SMS/Teams later |
| File attachments | Private local disk, authorized download route; photos, mp4/mov, PDF, Office; 50 MB each | S3/MinIO if storage grows |
| Backups + health check | spatie/laravel-backup nightly + `/up` | Server snapshots |
| Audit trail | Append-only `car_events` per CAR; model auditing for admin changes | owen-it/laravel-auditing |
| Testing | Pest feature tests, role × status × action matrix, sample CARs as UAT script | Pest browser tests later |

### 3b. Folder structure

```
app/
├── Enums/              # Role, CarStatus, CarAction, ComplaintType
├── Events/             # CarTransitioned
├── Http/Controllers/   # AttachmentController, Auth/LogoutController
├── Http/Middleware/    # EnsureUserIsActive
├── Listeners/          # RecordAccessLog
├── Livewire/
│   ├── Admin/          # Users, Matrix
│   ├── Auth/           # Login
│   ├── Cars/           # Index, Create (new + correct), Show, ResponseForm
│   ├── Dashboard/      # Index
│   └── Forms/          # UserForm
├── Models/             # User, Farm, BusinessLine, IssuedToUnit, Category, Subcategory, Car,
│                       # CarRound, CarEvent, CarResponse, CorrectiveAction, Attachment, AccessLog
├── Policies/           # CarPolicy
└── Services/           # CarWorkflow, CarNumberGenerator, DeadlineCalculator, ScaffoldData (temporary)

resources/views/
├── layouts/            # app (sidebar shell), guest
├── components/         # icon, pill, car-table, car-response, page-header, role-avatar
└── livewire/
```

### 3c. Data model — migrations & relationships

```
 1. farms              (name)
 2. business_lines     (name)                                       TABLE EGG, DOP
 3. issued_to_units    (business_line_id, name)
 4. categories         (business_line_id, name, response_days, implementation_days)
 5. subcategories      (category_id, name, description)            "what to report"
 6. users              (+ role, farm_id, approver_id → users, is_active)
 7. access_logs        (user_id, event, email, ip, user_agent)
 8. cars               (reference, status, current_round, requestor_id, farm_id, issued_to_unit_id,
                        category_id, subcategory_id, complainant, complaint_type, problem_details,
                        complaint_received_on, issued_on, response/implementation days + due dates,
                        revised_due_on, released_at, closed_at, voided_at)
 9. car_rounds         (car_id, number, opened_by_action, due_on)
10. car_events         (car_id, round, action, from_status, to_status, actor_id, note)   append-only
11. car_sequences      (year, last_number)
12. attachments        (attachable morph, collection, disk, path, original_name, mime, size, uploaded_by)
13. car_responses      (car_id, car_round_id, containment…, root_cause…, prepared_by, submitted_at)
14. corrective_actions (car_response_id, position, description, responsible, starts_on, ends_on)
    — planned: verifications (Phase 5), notifications (Phase 6), audits (Phase 9)
```

**Relationship map:**
```
BusinessLine 1─* IssuedToUnit        BusinessLine 1─* Category 1─* Subcategory
Farm 1─* User ── approver ──> User
Car *─1 Farm / IssuedToUnit / Category / Subcategory / User (requestor)
Car 1─* CarRound 1─1 CarResponse 1─* CorrectiveAction
Car 1─* CarEvent          Car, CarResponse 1─* Attachment (polymorphic)
```

**Modeling decisions worth locking in:**
- One status enum, not flags — the transition table makes illegal states unreachable.
- Rounds instead of overwriting: "not effective" and "not accepted" open a new round, so earlier answers stay.
- Deadlines are snapshotted at issuance — editing the matrix never moves an issued CAR's deadlines.
- Phase I fields lock after release (model guard), history rows can't be edited or deleted, CARs are voided, never deleted.
- Nobody approves a response they prepared; an approver's own response goes to their approver.

### 3d. Module ↔ mockup mapping

| Mockup tab (static) | Real route | Component |
|---|---|---|
| Login (pick a role) | `/login` (+ local role switcher) | `Auth\Login` |
| Dashboard | `/dashboard` | `Dashboard\Index` |
| My Queue / My Approvals | `/cars?view=mine` | `Cars\Index` |
| All CARs / Overdue | `/cars`, `/cars?view=overdue` | `Cars\Index` |
| CAR detail + actions + history | `/cars/{reference}` | `Cars\Show` + `Cars\ResponseForm` |
| New CAR | `/cars/create` (and `/cars/{reference}/edit` to correct) | `Cars\Create` |
| Users & Roles | `/admin/users` | `Admin\Users` |
| Category Matrix | `/admin/matrix` | `Admin\Matrix` |

### 3e. Build order

Each phase ends runnable and demoable. Don't start a phase before the previous one's tests pass.

#### Phase 0 — Foundation ✅ *(commit 2c0470d)*
- Livewire layout ported from the mockup, sign-in with no self-registration, local role switcher, `Role` enum and gates, access log, deactivated-user sign-out.

#### Phase 1 — Reference data ✅ *(commit 786ad74)*
- Farms, units, categories, sub-categories seeded from the requirement matrices; Users & Roles (approver chain, safeguards); Category Matrix editing.

#### Phase 2 — Core + state machine ✅ *(commit bf66543)*
- CARs, rounds, append-only history, reference numbers, snapshotted deadlines, `CarWorkflow` transition table tested for every role × status × action; sample CARs replayed through it.

#### Phase 3 — Phase I: initiation & release ✅ *(commit 86aff49)*
- File a CAR with attachments, release / reject with reason, correct & resubmit, Admin void; lists, detail and counts on real data.

#### Phase 4 — Phase II: response & approval ✅
- Containment, root cause (text or file), corrective actions with draft/submit; Step 10 approve / return with reason.

#### Phase 5 — Phase III: implementation & closure ✅
- Evidence upload, effectiveness check, final acceptance, "not accepted" with new end date.

#### Phase 6 — Notifications & reminders ✅
- In-app flag per transition, queued email, due-soon and overdue reminders.

#### Phase 7 — Dashboard & reporting ✅
- Real response / resolution time, monthly frequency, repeat offenses; filters by date, line, farm.

#### Phase 8 — Documents & import ✅
- Printable CAR form (Parts I–VI) and CSV export. *(Historical CAR import deferred.)*

#### Phase 9 — Maintenance ✅
- Nightly backups (`app:backup`), audit log of user and category changes with viewer.

#### Phase 10 — Hardening ✅
- [x] Every route/action authorized by policy, not menu visibility
- [x] No CAR reachable by link, download, print or export that its list would hide
- [x] Every transition has a reachable control and a real handler
- [x] Full happy path + every loop + every role combination tested
- [x] Attachment downloads and prints written to the access log
- [x] "My Approvals" excludes responses the approver may not review
- [ ] UAT: the three sample CARs walked to closure by real users

---

## 4. Testing & Hardening

| Status | Task | Notes |
|---|---|---|
| ✅ | Automated test suite green | 357 Pest tests, including an end-to-end lifecycle test and a full-sample-data smoke test |
| ✅ | Hardening checklist | Phase 10 list above (UAT item open) |
| ❓ | UAT / stakeholder acceptance | Script: Funa, Gallardo, Quitevis CARs end to end, one user per role |

---

## 5. Deployment

| Status | Task | Notes |
|---|---|---|
| ⬜ | Production environment/config ready | Internal server: PHP 8.4 (zip, pdo_mysql), MySQL 8, web server pointing at `public/`. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, MySQL credentials, `php artisan key:generate`. Then `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan db:seed --class=ReferenceDataSeeder --force` (**not** the demo seeders), `php artisan optimize` |
| ⬜ | Data & auth cutover | First IT Admin: `php artisan app:create-admin it@company.com`; then real users via Users & Roles with approver chains |
| ⬜ | Scheduler running | Cron / Task Scheduler every minute: `php artisan schedule:run` (reminders 07:00 weekdays, backups 01:00). Copy `storage/app/backups` off the server |
| ⬜ | Queue worker running | `php artisan queue:work` as a service (emails). In-app flags work without it |
| ⬜ | Mail settings | Set `MAIL_*` to the company SMTP when ready — until then emails go to the log |
| ⬜ | Go Live | After UAT sign-off |

---

## 6. Post-Launch

| Status | Task | Notes |
|---|---|---|
| ⬜ | Post-launch check-in | Ongoing once live |
| ⬜ | System turnover / sign-off | Per-role guides + admin runbook |

---

## Known Gaps / Deferred

- **Open decisions still on assumptions:**
  - Step 10 escalation uses the approver chain (provisional).
  - Who picks the category.
  - Calendar vs business days (calendar now).
  - CAR visibility (everyone sees all).
  - The Issued To ↔ farm link.
  - Multiple Issued To per CAR.
- **Pending outside the code:** UAT with real users, SMTP settings, server setup and go-live.
- **Deferred features:**
  - Importing historical paper CARs.
  - Removing attachments after submit.
  - Adding or renaming categories and sub-categories in the app (days are editable).
  - SSO, password self-service, 2FA.

---

**Status legend:** ✅ Done · 🟡 In Progress · ⬜ Not Started · ❓ Unknown · 🔵 Ongoing
