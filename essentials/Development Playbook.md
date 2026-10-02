# Development Playbook — mockup → UI scaffold → real build

A general, project-agnostic method for building a web app. It is distilled from how PANDA v2
was built, but nothing in it is specific to that project — reuse it anywhere.

> This is the *technical* method. It's paired with a **Development Plan Template** (this method,
> filled in per-project) and an **Asana Plan Template** (the outcome-level status view for
> supervisors/stakeholders) — see `Development Plan Template.md` and `Asana Plan Template.md`
> alongside this file. Each project keeps its own filled-in copies (plus a `ui-scaffold-checklist.md`)
> in that project's own repo, not in this vault.

The core idea: **settle every cheap decision before making any expensive one.**
Words are cheaper than HTML, HTML is cheaper than components, components are cheaper than
schemas, and schemas are cheaper than production data. So the order is:

```
Spec (words) → Mockup (static HTML) → UI scaffold (components, fake data) → Real build (data, auth, jobs)
```

---

## Stage 1 — Spec & mockup (the contract)

1. **Write the behavior spec in plain words first** — every state a record can be in, who can
   act on it, and where it goes next. If you can't write the table, you can't code it.
2. **Build one static HTML mockup** of the entire app: real navigation, real sample data,
   every screen, every modal. No framework — one HTML file (+ CSS/JS) you can open anywhere.
3. Treat the finished mockup as the **UI contract**:
   - Anything shown as a "tab" for convenience gets an HTML comment marking it
     `<!-- mockup only -->` — in the real app it becomes a separate route.
   - The sample data in it is the future **seeder data**, so real screens can be compared 1:1.
4. Settle **naming** (domain terms, statuses, roles) here, in conversation, before any code —
   renaming a concept in a mockup is a find-and-replace; renaming it in a schema is a migration.
5. Decide the small **UI grammar** rules once and write them down (e.g. row actions =
   View · one primary verb · overflow menu; no disabled buttons — a status label explains
   inaction; destructive actions = preview → type-to-confirm). Consistency comes from rules,
   not from memory.

**Exit criteria:** you can click through the whole product story in a browser, and the spec's
state table matches what the mockup shows.

## Stage 2 — UI scaffold (structure without data)

Port the mockup into the real framework as **pure UI — hardcoded sample data, no database,
no auth**. This stage turns one big HTML file into the app's permanent skeleton: layout,
routes, shared components, per-module components.

1. **Write a checklist file first** (`ui-scaffold-checklist.md`-style) breaking the port into
   steps, and keep it updated as the single source of "where are we". A good split:
   - Step 0 — shell: layout, theme, navigation, global JS, all routes stubbed
   - Step 1 — shared components (status pills, trackers, modals, menus, stat cards…)
   - Steps 2…N — one module per step, in workflow order
   - Step N+1 — auxiliary pages (help/reference, login, notifications)
   - Final step — polish: link behavior, empty states, placeholder routes, mockup cross-check
2. **One step per working session.** Every step ends with the app navigable in the browser.
3. Rules that keep the scaffold honest:
   - Sample data stays **identical to the mockup** so screens can be diffed by eye.
   - Stateless UI pieces are plain template components; only pieces with actual state become
     framework components. Promote later if behavior appears — never preemptively.
   - Buttons that would write data show a **toast saying nothing is persisted** — never fake a
     success silently.
   - Every screen the mockup showed as reachable must be reachable — **every transition needs
     a visible control** (a workflow arrow with no button is a bug factory).
   - Views that different roles see stay **separate components per role** even if their bodies
     are shared partials — each route is a separate door that later gets its own lock (policy).
   - Where a little real interactivity is cheap and demonstrative (a live selection, a
     type-to-confirm modal, a toggle panel), build it with real component state — it proves the
     framework wiring and survives into the real build.
4. **Verify each step mechanically** before calling it done: hit every new route, check for
   key content strings, click the interactive bits.
5. **Leave the step staged for review, then commit** with a uniform message (see Working agreements).

**Exit criteria:** every mockup screen exists as a routed component; all routes return 200;
the checklist is fully checked.

## Stage 3 — Real build (data under the skeleton)

Now the expensive parts, in dependency order — each phase ends runnable and tested:

1. **Domain core first, UI last.** The status enum + a state-machine service that owns every
   transition (who may do it, what happens next, throws on illegal moves) — written and
   **tested before any screen touches it**. The spec's state table from Stage 1 *is* the test list.
2. **Foundation:** auth (stub external dependencies behind a service you can swap), the
   permissions model, access logging (cheap now, painful to retrofit).
3. **Reference data:** the lookup tables and admin CRUD every later phase needs to test against.
   Seeders mirror the mockup's sample data.
4. **Modules in workflow order,** one phase each. Because the scaffold exists, "build the
   module" means: migrations/models/policies, then swap the component's hardcoded arrays for
   queries and its toast stubs for real actions — markup stays.
5. **Cross-cutting later phases:** documents/printing, notifications, scheduled jobs,
   backups, destructive-maintenance jobs.
6. **Hardening pass at the end**, driven by a written lessons checklist (see below).

**Exit criteria:** the happy path and every branch of the state table pass in tests; every
route/action is policy-authorized; the hardening checklist is all checked.

---

## Working agreements (any stage)

- **Slow and steady:** one checklist step per session. Finish and verify before starting the next.
- **End each step staged for review, not auto-committed.** When a step is done and verified,
  leave its changes uncommitted (staged is fine) and hand back control instead of committing
  immediately — the person building the project needs a moment to see exactly which files a
  feature touched while they're still learning the codebase; a commit that already happened
  collapses that into history where it's harder to inspect. Draft the commit message (you have
  the context on what the step actually delivered) and offer it, but don't run `git commit`
  until they say to. Uniform message either way: `feat: Step N - what it delivered` (pick
  "Step" for small increments; reserve "Phase" for the big stage-3 chunks); fixes between steps
  are `fix: …`; one logical change per commit.
- **The checklist file is the project's memory.** Update it (and any assistant/tooling memory)
  the moment a step lands, including deviations — a plan that no longer matches reality is
  worse than no plan.
- **When you deviate from the plan, update the plan** — mark the change *(actual: …)* rather
  than silently diverging.
- **Check current file state before editing.** Files get edited by hand between sessions;
  never assume yesterday's structure.
- **Authorize by policy on every entry point** (list, detail, download, print) — never by
  hiding buttons or menu items. Direct links are the attack you forgot.
- **Enforce invariants in code + query, not UI.** A disabled button is decoration.
- Report reality: if a test fails or a step is skipped, say so — don't paper over it.

## Security & correctness lessons (keep as a hardening checklist)

- [ ] Every route/action authorized by policy, not by menu visibility.
- [ ] No record reachable by direct link / file URL / print URL that the list would hide.
- [ ] Every workflow transition has a reachable UI control, and every control has a real handler.
- [ ] Deletion guards enforced in policy + query, not just a disabled button.
- [ ] Test coverage: full happy path, every backward branch, every permission × visibility
      combination, every bulk action.
- [ ] Sensitive fields encrypted (not hashed if you need the value back).
- [ ] Destructive jobs re-verify their counts server-side; typed confirmation is UX, not security.

## Environment gotchas (Windows / Laragon / Laravel — adjust per stack)

- Stray `php artisan serve` processes → compiled-view rename "Access is denied" 500s.
  Kill strays, `php artisan view:clear`.
- `php artisan route:clear` after every route change — the cache goes stale silently.
- Don't batch-edit source files with PowerShell 5.1 `Get-Content`/`Set-Content`: BOM-less
  UTF-8 is read as ANSI and non-ASCII characters (— · ₱ …) turn to mojibake. Use editor tooling.
- SPA-style navigation (`wire:navigate` etc.): bind global JS with **document-level event
  delegation** so behaviors survive DOM swaps; standalone pages (login, print) get plain links.
