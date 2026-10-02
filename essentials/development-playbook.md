# Development Playbook

How we take a workflow-driven internal system from requirements to production. It is project-neutral; the **Applied to CAR** box under each stage shows where the CAR Management System stands. The project's own phases and decisions live in `car-management-system-development-plan.md`.

The principle: **decide behavior in a mockup, build structure in a scaffold, only then build the real thing.** Each stage has an exit check so nothing is built on an unconfirmed assumption.

| Stage | Output | Who signs off |
|---|---|---|
| 0. Requirements | Behavior spec + real sample documents | Process owner |
| 1. UI concept | Single-file clickable mockup | Process owner + one person per role |
| 2. UI scaffold | Mockup ported into the real framework, hardcoded data | Process owner + dev lead |
| 3. Build | Phased modules, state machine first | Dev lead |
| 4. Hardening & UAT | Checklist passed, samples replayed | Process owner + role users |
| 5. Handover | Deployed system, runbook, training | IT + process owner |

---

## Stage 0 — Requirements

**Goal:** know what the process is *today* before designing the digital one.

1. Collect the spec **and real examples** (filled-in forms, screenshots, chat threads). Real examples expose what the spec leaves out.
2. Read the examples against the spec and write down every difference — fields on the form that the spec never mentions, steps the flowchart has that the numbered list lacks, data-entry mistakes (wrong years, misspelled names).
3. List the people who touch the process and what each one *does* (not their job title).
4. Write the open questions down with an owner each. Do not guess answers silently.

**Exit check:** every spec step is mapped to a role; every example document has been read; the open-question list exists.

> **Applied to CAR:** done. The 13-step requirement, the flowchart and both matrices were read against three real CARs (Funa, Gallardo, Quitevis). That surfaced the missing release approval, the Type of Complaint field, backdated issue dates, video attachments and the Part V/VI sign-offs. Open questions are in the Plan's §7.

---

## Stage 1 — UI concept (mockup)

**Goal:** make the process visible so people can correct it cheaply.

- One self-contained HTML file. No backend, sample data in a script block, a role picker instead of a login.
- Model **every role's queue**, every status, every button. If a transition has no button in the mockup, it will not exist in the product.
- Settle the **action grammar** here: how lists, detail pages, forms and approval buttons behave. The real app inherits it.
- Name roles and statuses by **function**, not by org chart. Job titles change; functions do not.
- Keep deadline logic and reference-data lookups real (they are cheap) so reviewers can see the effect.
- Walk **one real sample** through every role as a swimlane before showing it to users; reviewers find the gaps in the first ten minutes.

**Exit check:** each role can finish its part of a sample CAR; no status is a dead end; the process owner has seen it.

> **Applied to CAR:** done. `car-management-system-mockup.html` has six roles (Requestor, Requestor Approver, Responder, Responder Approver, Monitor, IT Admin), the full Phase I → III flow with reject and not-accepted loops, a dashboard, and Admin screens for users and the category matrix. The Step 10 escalation path is deliberately left out until Plan §7 #1 is answered.

---

## Stage 2 — UI scaffold

**Goal:** the real application shell exists, looks like the mockup, and every screen is reachable — still on fake data.

1. Install the framework pieces the UI needs (components, CSS pipeline) and lift **design tokens** from the mockup (colors incl. dark mode, spacing, type).
2. Build the layout once: sidebar, topbar, role-aware navigation.
3. Create **one component per mockup view**, route-per-view, markup copied from the mockup, data hardcoded or seeded. Buttons call stub handlers.
4. Replace the mockup's login with real auth plus a **dev-only role switcher** so reviewers can still demo each role.
5. Move the mockup's sample data into **seeders** — they become development fixtures and test data.
6. Record every change from the mockup as *(actual: …)* in the plan's status update.

**Rule:** the scaffold must contain **no domain logic**. If a component starts deciding what happens next, that belongs in a service (Stage 3).

**Exit check:** every route in the plan's UI-mapping table loads; the role switcher shows each role's own queue; stakeholders can click through it.

> **Applied to CAR:** **done (Oct 2, 2026)** as part of Plan Phase 0. Every mockup view is a Livewire route on the mockup's markup and tokens. Real sign-in replaces the role picker, with a local-only switcher listing one seeded account per role. CAR data comes from `ScaffoldData`, and workflow buttons are authorized stubs that name the phase that wires them up.

---

## Stage 3 — Build

**Goal:** replace the scaffold's fakes with real behavior, module by module.

**Order of work**

1. **Foundation** — auth, roles, gates, access log.
2. **Reference data** — lookups and the admin screens that edit them, so every later phase has something to test against.
3. **State machine before UI.** Write the transition table (status × action × role → next status) on paper, review it with the process owner, then code it as one service with a test for every transition *and* every forbidden one.
4. **Modules in workflow order** — one phase per stage of the business process.
5. **Notifications, dashboard, documents** — after the workflow is real, because they read workflow data.
6. **Maintenance** — backups, audit, destructive operations (always preview → confirm → execute).

**Per-module routine**

1. Migration + model + enum.
2. Policy (who may see / act), then the transition(s) in the state machine.
3. Failing test → implement → passing test.
4. Swap the scaffold component's hardcoded data for real queries and its stub actions for service calls. **Keep the markup.**
5. Demo the phase to the process owner. Do not start the next phase until its tests pass.

**Standing rules**

- Authorization lives in policies and is called from **every** entry point (action, route, download, print, export). Menu visibility is not security.
- Only the state-machine service changes the status column.
- Data that must not change (issued details after release, deadlines after creation) is protected in the policy **and** the model, never just by disabled inputs.
- Money-saving shortcuts that destroy history (hard deletes, overwriting a field on a retry) are not allowed; use voids and rounds.
- One database transaction per transition; write the history event in the same transaction.

**Exit check (per phase):** tests green, demo done, mockup behavior matches, deviations logged.

---

## Stage 4 — Hardening & UAT

**Goal:** prove nothing is reachable that shouldn't be, and that real users can finish real work.

- Run the plan's hardening checklist in full.
- Test **every role × status permission combination** (generate it from the transition table so it cannot drift).
- Try to break access: open direct URLs, download links and print routes as the wrong role.
- **Replay the real sample documents** end to end, with the real users in their roles, on a copy of production data.
- Capture what users stumble on; fix before launch, not after.

**Exit check:** checklist all ticked; every sample document completed through closure; no open high-severity defects.

> **Applied to CAR:** UAT script = the three sample CARs, entered and walked from Phase I to "Closed — Accepted", plus one run through each loop (reject at release, return at Step 10, not effective, not accepted).

---

## Stage 5 — Handover

- Deployment steps, environment variables, scheduler and queue worker setup written down and rehearsed once.
- Backups running **and one restore tested**.
- A one-page guide per role ("what is in my queue, what do I press").
- Admin runbook: add or deactivate a user, change the matrix, import records, read the audit log.
- A named owner for the system, and a place to log requests for changes.

**Exit check:** someone other than the developer can deploy, restore a backup and onboard a new user from the documents alone.

---

## Working agreements

- **Mockup is the UI contract.** Change the mockup first, then the product. Anything that is only in the mockup (login role picker, simulated data) is labeled so.
- **Plan is the memory.** Deviations are logged inline as *(actual: …)* when they happen, not reconstructed later.
- **Open questions have owners and a "needed by" phase.** A phase does not start with an unanswered question that blocks it.
- **Real samples beat imagined cases.** Keep them as seed data and as the UAT script.
- **Small, demoable steps.** If a phase cannot be demoed, it is too big or in the wrong order.
