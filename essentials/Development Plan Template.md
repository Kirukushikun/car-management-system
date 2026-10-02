# [Project Name] — Development Plan

**Stack baseline:** [framework + version] · [key libraries] · [database] (env) · [build tool]
**References:** `system-overview.md` (behavior spec) · `[project]-ui-concept.html` (UI contract — tabs marked "mockup only" become real routes)

> **Status update:** note here whether a UI-scaffold pass (see the Development Playbook, Stage 2)
> was completed before this plan's phases, and how that changes where §5 starts from. Log any
> deviations made during the scaffold as *(actual: …)* inline below as they happen.

---

## 1. Recommended tech per function

Fill in one row per cross-cutting concern the project needs a decision on. The "Default" column
is the recommendation; "Alternative" is the fallback if the default fights the project.

| Function | Default recommendation | Alternative / notes |
|---|---|---|
| Auth | | |
| Roles & permissions | | |
| Core domain state machine (if any) | | |
| Notifications | | |
| File attachments | | |
| Backups + health check | | |
| Import/export (if any) | | |
| Audit trail | | |
| Access log | | |
| Sensitive fields | | |
| Danger zone (destructive ops) | | |
| Queue driver | | |
| Testing | | |

---

## 2. Folder structure

```
app/
├── Enums/            # domain enums — one per fixed set of states/types
├── Models/            # see §3 for fields & relationships
├── [ui-layer]/         # one folder per module = one sidebar item
├── Services/           # state machines, external integrations, business logic
├── Policies/           # authorization — the single source of truth per model
├── Jobs/               # queued/destructive/scheduled work
├── Observers/          # audit/side-effect hooks
└── Listeners/          # event listeners

resources/views/…      # or equivalent for the chosen framework
routes/…                # thin: route → component/controller, all behind middleware
```

**Conventions to commit to early**

- Authorization happens in **policies**, called from every entry point — never only from
  navigation/menu visibility.
- Every table/list gets a consistent action grammar (settled during the mockup stage).

---

## 3. Data model — migrations & relationships

### Migration order (respects FK dependencies)

```
1. [lookup table]
2. [lookup table]
3. users              (…)
...
```

### Relationship map

```
[Entity]  1─* [Entity]  1─* [Entity]
```

### Modeling decisions worth locking in

- Note any single-column-vs-split-columns decisions (e.g. one status enum instead of several
  overlapping flags) and why.
- Note any self-referencing / chain relationships and what they power.
- Note any guard that must exist in **both** policy and query/DB constraint, not UI alone.
- Note any gating rule (e.g. confidentiality/visibility tiers) and every entry point it must
  cover: list, direct route, download, print, etc.

---

## 4. UI module ↔ mockup mapping

| Mockup tab (static) | Real route | Component |
|---|---|---|
| | | |

---

## 5. Build order

Each phase ends runnable and demoable. Don't start a phase before the previous one's tests pass.

> **Note (post-scaffold):** if a UI scaffold exists, "build X module" means: add the
> migrations/models/policies, then swap the component's hardcoded data for real queries and its
> stubbed actions for real ones — keeping markup and sample-data-as-seeders intact.

### Phase 0 — Foundation *(everything depends on this)*
- Framework scaffold, app layout, auth stub, roles/permissions gates, access log listener.

### Phase 1 — Reference data
- Lookup tables, admin CRUD every later phase needs to test against.

### Phase 2 — Core domain + state machine
- Core migration(s), enums, the state-machine service with a full transition table +
  tests **before any UI touches it**.

### Phase 3+ — Modules in workflow order
- One phase per module/stage of the workflow.

### Phase N — Documents & notifications
- Printing/export, notification channels, scheduled jobs.

### Phase N+1 — Maintenance
- Backups, reference-value management, destructive-operation jobs (preview → confirm → execute).

### Phase N+2 — Hardening *(checklist-driven)*
- [ ] Every route/action authorized by policy, not menu visibility.
- [ ] No record reachable by direct link/URL that the list would hide.
- [ ] Every workflow transition has a reachable control with a real handler.
- [ ] Test coverage: full happy path + every branch + every permission combination.
- [ ] Sensitive fields encrypted where reversibility is required.

---

## 6. Suggested first week

1. Phase 0 in full.
2. Early migrations + seeders that mirror the mockup's sample data.
3. State machine transition table on paper first, then as tested code.
