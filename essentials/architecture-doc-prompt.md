# Prompt: Architecture Design Document — CAR Management System

> **How to use:** paste this whole file into an AI assistant. If the assistant can read files, also attach `car-management-system-development-plan.md`, `development-playbook.md` and the requirements PDF. Everything the AI needs is written below; the attachments are for detail.

---

## Your role

You are a software architect writing the **architecture design document** for one real module: the **CAR (Corrective Action Request) Management module** of an internal system for Brookside Group of Companies (poultry: TABLE EGG and DOP / day-old-chick operations). The document is reviewed by a supervisor as evidence that the author *led the architectural design*. That means three things must be visible:

1. **Diagrams** that explain the system at several levels of detail.
2. **Technology choices**, each with a reason tied to this project.
3. **Trade-off analyses**, showing the options that were considered and rejected, and why.

Write for a technical reader who knows web development but not this project. Prefer precise, plain sentences over buzzwords.

## Ground rules

- **Use only the facts in this prompt.** Where something is unknown (hosting, user counts, SSO), do not invent it. Write it as an **Assumption** (and say what would change if it is wrong) or list it under **Open decisions**.
- **Every diagram is Mermaid** inside ```` ```mermaid ```` blocks, so it renders on GitHub and in VS Code. Keep each diagram to one idea. Use only stable Mermaid syntax (`flowchart`, `sequenceDiagram`, `stateDiagram-v2`, `erDiagram`, `C4Context`/`C4Container`/`C4Component` — if unsure C4 syntax renders, draw C4 as a `flowchart` with labeled boxes). Re-check every block for syntax errors before answering.
- **Every diagram gets one short paragraph** below it: what to notice.
- **Every technology choice and trade-off states the criteria used.** Use these selection criteria consistently:
  1. Requirements & constraints
  2. Domain & problem complexity
  3. Team expertise
  4. Integration with other systems
  5. Scalability & performance (realistic for this load)
  6. Testability
  Add *Cost / operational burden* and *Security* where relevant.
- Keep the document honest about scale: this is a **small internal workflow system**, not a high-traffic product. Recommending more infrastructure than the load needs is a mistake, not a strength.

---

## Project facts

### Purpose

Digitalize the CAR process for TABLE EGG and DOP operations — from issuing a complaint, through root-cause and corrective-action planning, implementation, verification and closure — with automatic deadline calculation, approval routing, notifications and a monitoring dashboard. Today CARs are Word/PDF forms with photos, screenshots and videos pasted in, passed around by hand.

### Load and context (use as assumptions, label them)

- Users: roughly 10–40 internal staff (sales agents, approvers, farm responders, QA, IT).
- Volume: about 8–15 CARs per month (sample dashboard data: 7–14 per month).
- Attachments are the heaviest data: photos, phone videos, chat screenshots, photographed dispatch forms.
- Single company, single timezone (Philippines), internal use, office and farm sites (Manila corporate office; Tarlac farm office).
- Development machine: Windows with Laragon. **Production hosting is not decided.**

### Roles (function-based, not job titles)

| Role | Does |
|---|---|
| Requestor | Files the CAR (Phase I) for a customer/complainant; fixes it if returned |
| Requestor Approver | Releases or rejects a CAR before it goes out; gives final acceptance that closes it |
| Responder | Writes containment, root cause, corrective action (Phase II); uploads implementation evidence (Phase III) — scoped to one farm |
| Responder Approver | Approves root cause & corrective action; validates effectiveness — scoped to one farm |
| Monitor | Read-only dashboard and reports |
| IT Admin | Users, roles, category/deadline matrix; read-only on CARs |

### Workflow (13 steps in three phases)

- **Phase I — Initiation:** auto reference number (`CAR-YYYY-NNNN`); Issued To (unit) → business line (TABLE EGG / DOP) → category → sub-category; deadlines auto-computed: *Response = issued + response days*, *Implementation = issued + implementation days* (from the matrix); problem details; optional attachments; release approval.
- **Phase II — Response:** interim containment (dates, responsible), root cause (text or file), corrective-action lines (start = containment end; end = implementation deadline); approval (Step 10 — approver is one level above whoever prepared the response).
- **Phase III — Closure:** implementation evidence upload; effectiveness check; final acceptance. "Not accepted" sets a new end date and loops back to evidence upload.

### Status transition table (the core state machine)

| From | Action | Who | To |
|---|---|---|---|
| — | Submit new CAR | Requestor | Awaiting Release |
| Awaiting Release | Approve & release | Requestor Approver | Awaiting Responder |
| Awaiting Release | Reject | Requestor Approver | Returned to Requestor |
| Returned to Requestor | Resubmit | Requestor | Awaiting Release |
| Awaiting Responder / Returned to Responder | Submit response | Responder | Awaiting Responder Approval |
| Awaiting Responder Approval | Approve | Responder Approver | Awaiting Implementation |
| Awaiting Responder Approval | Return for revision | Responder Approver | Returned to Responder |
| Awaiting Implementation | Upload evidence | Responder | Awaiting Effectiveness Check |
| Awaiting Effectiveness Check | Mark effective | Responder Approver | Awaiting Requestor Approval |
| Awaiting Effectiveness Check | Not effective | Responder Approver | Returned to Responder |
| Awaiting Requestor Approval | Accept | Requestor Approver | Closed — Accepted |
| Awaiting Requestor Approval | Not accepted (+ new end date) | Requestor Approver | Open — Not Accepted |
| Open — Not Accepted | Upload new evidence | Responder | Awaiting Effectiveness Check |
| any open status | Void (with reason) — *planned* | IT Admin | Voided |

### Deadline matrix (excerpt)

| Business line | Category | Response days | Implementation days |
|---|---|---|---|
| TABLE EGG | Production Related | 3 | 10 |
| TABLE EGG | Compliance & Standards | 1 | 5 |
| TABLE EGG | Logistics & Distribution Transport | 1 | 5 |
| DOP | Production Related | 3 | 10 |
| DOP | Compliance & Standards | 1 | 3 |
| DOP | Preparation & Distribution Transport | 1 | 5 |
| DOP | Sales & Order Management | 1 | 5 |

### Dashboard requirements

Repeat offenses by category and sub-category; open vs closed; response time (response submitted − issued); resolution time (closed − issued); frequency of CAR issuance (by month, unit, agent); overdue list.

### Chosen stack (already built in Phase 0)

- **Laravel 13** (PHP 8.4), **Livewire 4** (server-rendered reactive UI, class-based components), **Tailwind 4**, **Vite 8**
- **Pest 5** tests, **Pint** formatting, **Laravel Boost** (AI dev guidelines)
- **SQLite** in development → **MySQL** planned for production
- **Database queue** driver; Laravel scheduler for reminders
- Auth: Laravel session auth, no self-registration (IT Admin creates accounts); access log of sign-in/out/failed
- Authorization: `Role` enum + gates now; `CarPolicy` per record in Phase 2

### Planned internal structure (modular monolith, layered)

```
Livewire components (UI, one folder per sidebar module: Dashboard, Cars, Admin)
  → Policies (authorization, every entry point)
  → Services: CarWorkflow (state machine, the only code that changes status),
              DeadlineCalculator, CarNumberGenerator, DashboardMetrics, CarImporter
  → Eloquent models + Enums (Role, CarStatus, ComplaintType, CarEventType)
  → MySQL / private file storage
Side channels: Events → Listeners (access log, notifications) · Jobs (reminders, backups, import) · Observers (audit)
```

### Data model (planned)

Lookup: `farms`, `business_lines`, `issued_to_units`, `categories` (response_days, implementation_days), `subcategories`.
People: `users` (role, scope/farm, `approver_id` → users, is_active).
Core: `cars` (reference, status, issued_on, complaint_received_on, complaint type, snapshotted `response_due_on`, `implementation_due_on`, `revised_due_on`), `car_rounds` (one per Phase II/III attempt), `corrective_actions`, `verifications`, `attachments` (polymorphic), `car_events` (append-only history), `car_sequences`.
Ops: `notifications`, `jobs`, `access_logs`, `audits`.

Key decisions already made:
- One status enum instead of overlapping flags; current owner derived from status + farm.
- Each loop back creates a new `car_round` (history and metrics stay correct).
- Deadlines are **snapshotted at creation** — editing the matrix never moves existing deadlines.
- Phase I fields are immutable after release (policy **and** model guard).
- No hard deletes of CARs — void with a reason.
- Attachments only through an authorized download route (never public URLs).

### Open decisions (do not resolve them — analyse them)

1. Step 10 routing: approver chain (`approver_id` + escalation) vs a third approver role.
2. Who picks the category (spoilage fits two categories with different deadlines).
3. Calendar vs business days for deadlines.
4. Visibility: everyone sees all CARs vs farm/department-only.
5. Production hosting: on-premise server vs cloud VM (IaaS) vs PaaS / Laravel Cloud.
6. Notification channels beyond in-app + email (SMS/Viber/Teams).
7. SSO with company accounts vs local passwords.

---

## Document to produce

Produce **one Markdown document** with these sections, in this order.

### 1. Overview
Purpose, scope of the module (what is in / out), stakeholders, and 4–6 **quality attributes** ranked for this system (expect: auditability, correctness of workflow, security of attachments, maintainability by a small team, usability on phones at farm sites, availability during office hours). One line on why each matters here.

### 2. Architectural drivers
Table of functional drivers, quality drivers and constraints (team size, budget, Windows/Laragon dev, internal network, undecided hosting). Mark assumptions.

### 3. Architecture style decision *(trade-off analysis)*
Compare **Monolith (modular, layered)** vs **Microservices** vs **Service-Oriented split** (e.g. separate notification/file service) using the six criteria above, as a **weighted decision matrix** (weights 1–3, scores 1–5, totals). Explain:
- Why a modular monolith wins at this scale (refer to the **Scale Cube**: which axis would ever be needed — X-axis duplication behind a load balancer is enough; Y-axis decomposition and Z-axis sharding are not justified at ~15 CARs/month).
- What "monolith hell" would look like here and which structural rules prevent it (module folders, services owning logic, policies, one state machine).
- The **evolution path**: which part would be extracted first if ever needed (likely notifications or file storage) and what signal would trigger it.

### 4. Diagrams
Produce each of these, each with its short explanation:

1. **System context (C4 level 1):** users by role, the CAR system, email server, (optional) SMS/Viber gateway, backup storage, future SSO.
2. **Container diagram (C4 level 2):** browser (Livewire/Alpine), Laravel web app, queue worker, scheduler, MySQL, private file storage, mail server.
3. **Component diagram (C4 level 3) of the Laravel app:** Livewire modules → Policies → Services (CarWorkflow, DeadlineCalculator, CarNumberGenerator, DashboardMetrics, CarImporter) → Models → DB; Events/Listeners, Jobs, Notifications, Observers on the side.
4. **Layered view:** presentation / application (services, policies) / domain (models, enums, state machine) / infrastructure (DB, storage, mail, queue) — show allowed dependency direction.
5. **CAR state machine:** `stateDiagram-v2` from the transition table, including both loops and the planned Voided state; label transitions with action and role.
6. **Workflow swimlane:** one lane per role, Phase I → III, using the real sample case "customer Rollie Funa reports spoiled big dirty eggs" as the walk-through.
7. **Sequence diagrams (three):**
   a. Requestor submits a CAR → reference number generated in a DB transaction → deadlines snapshotted → event recorded → Requestor Approver notified (queued).
   b. Responder Approver approves at Step 10 → policy check → CarWorkflow transition → `car_events` row in the same transaction → notification job → mail.
   c. "Not accepted" loop → new end date → new `car_round` → Responder and Responder Approver flagged → overdue check uses `revised_due_on`.
8. **Entity–relationship diagram:** the tables listed in the data model with key relationships and cardinalities.
9. **Deployment diagram:** the recommended option (see §6), plus a small comparison sketch of the alternatives.
10. **Security / authorization view:** request → auth middleware → active-user check → gate/policy → component action → audit/access log; plus the attachment download path.

### 5. Technology choices
One table: *Concern · Choice · Alternatives considered · Why this choice (criteria) · Risk / mitigation*. Cover at least: backend framework, UI approach (**Livewire vs Inertia+Vue/React SPA vs Blade-only**), database (**SQLite dev / MySQL prod vs PostgreSQL**), state machine (**hand-written transition table vs `spatie/laravel-model-states`**), roles (**enum column vs `spatie/laravel-permission`**), queue (**database vs Redis**), file storage (**local private disk vs S3/MinIO**), notifications (**database + mail vs push/SMS**), PDF/print, backups, testing, audit trail.

### 6. Deployment options *(trade-off analysis)*
Compare along the spectrum **On-premise → IaaS / VM → Containers (Docker) → PaaS (incl. Laravel Cloud) → Serverless** for *this* system: cost, control, maintenance burden on a small IT team, data residency of customer complaints, file/video storage, backups, uptime, farm-site access over the internet vs VPN. Give a recommendation **and** the conditions under which a different option wins. State clearly that hosting is an open decision.

### 7. Design patterns used in the code
Name the concrete patterns and where they live, e.g.: **State** (CarWorkflow + CarStatus), **Observer** (events/listeners, model observers for audit), **Factory** (model factories, CarNumberGenerator), **Strategy** (notification channels; possible calendar vs business-day deadline calculators), **Policy/Guard** (authorization), **Repository-free Active Record** (Eloquent) and why that is acceptable here, **Append-only event log** (`car_events`). One or two sentences each — why it fits.

### 8. Cross-cutting concerns
Security (auth, authorization at every entry point, private attachments, CSRF, rate-limited login, access log), auditability, data retention, backups & restore test, error handling & logging, performance (expected load, indexes on status/due dates), accessibility & mobile use at farm sites.

### 9. Architecture Decision Records
Write 6–8 short ADRs (Context · Decision · Alternatives · Consequences · Status), at minimum:
- ADR-001 Modular monolith over microservices
- ADR-002 Livewire for the UI
- ADR-003 Single status enum + hand-written state machine service
- ADR-004 Function-based roles instead of job-title roles
- ADR-005 Deadlines snapshotted at CAR creation
- ADR-006 Rounds instead of overwriting Phase II/III data
- ADR-007 Private attachment storage with authorized downloads
- ADR-008 Hosting — **Proposed**, pending decision

### 10. Risks and open decisions
Table: risk / open decision · impact · likelihood · mitigation or who decides · needed by (phase). Include the seven open decisions above, plus: large video uploads, single-server failure, key-person dependency on one developer, data entry mistakes (wrong year on a form), adoption by farm users.

### 11. Roadmap alignment
Map the architecture to the build phases (0 Foundation ✅ done · 1 Reference data · 2 Core state machine · 3 Phase I · 4 Phase II · 5 Phase III · 6 Notifications · 7 Dashboard · 8 Documents & import · 9 Maintenance · 10 Hardening & UAT) — one line each on which architectural element that phase delivers.

### Appendix
Glossary (CAR, DOP, Table Egg, containment, root cause, round, Requestor, Responder…) and the role × action permission matrix as a table.

---

## Quality checklist (check before answering)

- [ ] Every diagram renders as valid Mermaid and has an explanation.
- [ ] Every technology choice names alternatives and the criteria behind the choice.
- [ ] Trade-off sections end with a clear recommendation **and** the conditions that would change it.
- [ ] Nothing about hosting, SSO, user count or SMS is presented as decided.
- [ ] The state diagram matches the transition table exactly (10 statuses + planned Voided).
- [ ] The recommended architecture is proportionate to ~10–40 users and ~15 CARs/month.
- [ ] No section repeats another; total length is readable in under 30 minutes.
