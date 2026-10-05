# [Project Name] — Development Plan

**Stack:** [e.g. Laravel 13 · Livewire 4 · MySQL/MariaDB · Tailwind]
**Status:** Planning / In Dev / Live / Maintenance
**Last updated:** [YYYY-MM-DD]
**Repo:** [link] · **Prod URL:** [link]

---

## Objectives

*What this project aims to accomplish and provide. These are descriptive goals, not tasks — 3 milestones max.*

1. **[Milestone 1]** — e.g. "Eliminate paper-based disinfection slip recording by replacing manual logs with a fully digital documentation system."
2. **[Milestone 2]** —
3. **[Milestone 3]** —

---

## 1. Planning

| Status | Task | Notes |
|---|---|---|
| ⬜ | Analyze requirements / problem statement | |
| ⬜ | Set up Git repo & local dev environment | Framework scaffold, `.env`, DB connection |
| ⬜ | Define scope — what's in, what's explicitly out | |

---

## 2. Design

| Status | Task                            | Notes                                                                     |
| ------ | ------------------------------- | ------------------------------------------------------------------------- |
| ⬜      | Build static HTML/UI mockup     | Every screen as tabs/sample data — this becomes the UI contract for Build |
| ⬜      | Settle domain naming / glossary | Status enums, roles, key terms — consistent with the mockup               |
| ⬜      | Data model / database design    | Migrations, relationships — derived *from* the mockup, done after it      |

---

## 3. Build

*This is the working spec — the actual instruction set your coding agent follows.*

### 3a. Recommended tech per function

| Function | Default | Alternative / notes |
|---|---|---|
| [e.g. Auth] | | |
| [e.g. Roles & permissions] | | |
| [e.g. Core status flow] | | |
| [e.g. Notifications] | | |
| [e.g. File attachments] | | |
| [e.g. Backups + health check] | | |
| [e.g. Audit trail] | | |
| [e.g. Testing] | | |

### 3b. Folder structure

```
app/
├── Enums/
├── Models/
├── Livewire/          # or Http/Controllers, if not Livewire
├── Services/
├── Policies/
├── Jobs/
├── Observers/
└── Listeners/

resources/views/
├── layouts/
├── components/
└── livewire/
```

### 3c. Data model — migrations & relationships

```
1. [table]   (columns...)
2. [table]   (columns...)
```

**Relationship map:**
```
[Entity]  1─*  [Entity]  1─*  [Entity]
```

**Modeling decisions worth locking in:**
- [e.g. one status enum, not split columns — keeps illegal states unrepresentable]
- [e.g. delete guards belong in the policy AND a DB-level constraint]

### 3d. Module ↔ mockup mapping

| Mockup tab (static) | Real route | Component |
|---|---|---|
| | | |

### 3e. Build order

Each phase ends runnable and demoable. Don't start a phase before the previous one's tests pass.

#### Phase 0 — Foundation
-

#### Phase 1 — Reference data
-

#### Phase 2 — Core + state machine
-

#### Phase 3 — [next module]
-

#### Phase N — Hardening *(carry lessons from prior projects forward as a checklist)*
- [ ]
- [ ]

---

## 4. Testing & Hardening

| Status | Task | Notes |
|---|---|---|
| ⬜ | Automated test suite green | |
| ⬜ | Hardening checklist | Config/security checklist before going live |
| ❓ | UAT / stakeholder acceptance | |

---

## 5. Deployment

| Status | Task | Notes |
|---|---|---|
| ⬜ | Production environment/config ready | |
| ⬜ | Data & auth cutover | |
| ⬜ | Scheduler running | Backups, cron jobs |
| ⬜ | Go Live | |

---

## 6. Post-Launch

| Status | Task | Notes |
|---|---|---|
| 🔵 | Post-launch check-in | Ongoing — steady stream of fixes/features expected |
| ⬜ | System turnover / sign-off | |

---

## Known Gaps / Deferred

*Things intentionally not done yet — the honest list, not a to-do list.*

-
-

---

**Status legend:** ✅ Done · 🟡 In Progress · ⬜ Not Started · ❓ Unknown · 🔵 Ongoing
