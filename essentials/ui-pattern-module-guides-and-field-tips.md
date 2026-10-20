# UI Pattern — Module Guides & Field Tips

A portable standard for in-app help, so every system explains itself the same way. Each system keeps its own look; the **behaviour, layout, placement and writing rules** are shared.

> Reference implementation: CAR Management System (Laravel 13 + Livewire 4 + Alpine.js + Font Awesome 7). The code below is copy-ready for Laravel/Blade; section 9 covers other stacks.

---

## 1. The idea in one paragraph

Help appears **only when someone asks for it**, and **where they are looking**. A question about one field is answered next to that field (a **field tip**). A question about the page or module as a whole is answered in one side panel opened from the page header (a **module guide**). Nothing is stacked under fields, and no page opens with a wall of instructions.

| Question the user has | Where the answer lives | Component |
|---|---|---|
| "What goes in this field?" | ⓘ at the far right of the field's label | **Field tip** |
| "What is this page for / what happens next / what does this number mean?" | **Guide** button in the page header → side panel | **Module guide** |
| "Which option fits my case?" (long reference lists) | A **reference tab** inside the module guide | Guide reference tab |

---

## 2. Rules (the part that must be the same in every system)

### 2.1 Field tips

**Placement**
- An info icon (ⓘ) at the **far right of the field's label row**, on the same line as the label.
- If the label row has other controls (e.g. "Remove"), they sit **just before** the ⓘ; the ⓘ is always last.
- The tip bubble opens **below the icon, right-aligned to it**, over the content (never pushing the layout).

**Behaviour**
- Opens on **hover** (mouse), **focus** (keyboard Tab) and **tap/click** (touch). Click toggles.
- Closes on mouse leave, blur, click outside, or tapping the icon again.
- Only the bubble appears — no page movement, no modal, no scroll.

**Content**
- **1–2 sentences, ≤ 30 words.** Say what to enter and any rule that isn't obvious (limits, format, who sees it).
- May include live values (e.g. "…no later than the implementation deadline, Oct 13, 2026.").
- Don't repeat the label. Don't explain the whole process — that belongs in the guide.
- Every field that isn't self-explanatory gets one; trivial fields (e.g. "Name" on a login form) don't need one.

**Replaces**: static grey hint lines under fields. Keep a hint line under a field **only** when it shows *live, changing* information (e.g. "Business line: TABLE EGG", a calculated deadline).

### 2.2 Module guide

**Placement**
- A **Guide** button (ⓘ icon + "Guide") in the **page header, on the right**, as the **first** action (before Print, Export, filters).
- One Guide button per page. Never put guide buttons inside cards or sections.
- The guide opens as a **side panel sliding in from the right**, full height, ~460px wide (full width on phones), over a dimmed backdrop. The page underneath is untouched — form entries are never lost.

**Layout of the panel (top to bottom)**
```
┌──────────────────────────────────────┐
│ <Module> guide                     ✕ │  title + one-line subtitle, close button
│ <one-line description>               │
│ [ Phase I • now | Phase II | Phase III ]  ← optional: stage switch (section 2.3)
│ Overview   Reference tab              │  ← topic tabs (underlined text tabs)
│──────────────────────────────────────│
│ content (scrolls)                    │
└──────────────────────────────────────┘
```

**Behaviour**
- Opens from its header button; closes on ✕, **Esc**, or clicking the backdrop.
- Focus moves to ✕ on open (keyboard users can close immediately).
- **Only one guide is open at a time** — opening another closes the current one.
- Tabs keep their state while the panel is open; the panel always opens on its **first tab**.

**Content — maximum two topic tabs**
1. **Overview** (always first): a numbered flow of **at most 3 steps**, each = **bold step + one short line**. Optionally one closing line (e.g. "Not sure about a field? Use the ⓘ at the right of its label.").
2. **One reference tab**, only if it helps. Pick the shape that fits:
   - *Definitions* — a short list: **term** + one line (e.g. "Reading the figures" on a dashboard).
   - *Comparison cards* — 2–4 cards, each **title + one line + one italic example** (e.g. "What goes where").
   - *Catalogue* — long lists (categories, codes, products): **collapsed groups** with a summary line, expand to see items, with a **search box** that filters items and auto-opens matching groups. If the page already has a context (e.g. a business line picked), open on it.

**Never in a guide**: field-by-field instructions (those are field tips), policies or long prose, anything that changes per record (show that on the page itself).

### 2.3 Optional: stage switch (for records that move through stages)

Use when a record goes through phases/stages and each has its own guide (e.g. Phase I/II/III).
- On the record's page, the Guide button opens the guide for the **record's current stage**.
- A **segmented control** under the panel title switches between stage guides; the record's current stage carries a small **NOW** tag. The stage being read is the raised/selected segment.
- On a page that belongs to one stage only (e.g. the "new record" form), show that stage's guide **without** the switch.

### 2.4 Writing style (both components)
- Plain words, second person ("you"), present tense. Name roles the way users say them.
- Lead with the action or the answer. One idea per sentence.
- Examples are real-looking and short, introduced with "e.g.".
- No marketing tone, no exclamation marks, no emoji (icons come from the icon set).

---

## 3. Theming contract — keep each system's own look

The pattern owns **structure and behaviour**; the host system owns **fonts, sizes, spacing, colours, radii and shadows**. All styling reads from a small set of `--help-*` variables. Each system maps them once to its own design tokens.

| Variable | Role | Map to the host's… |
|---|---|---|
| `--help-font` | text font | body font (usually `inherit`) |
| `--help-surface` | panel / bubble background | card/page background |
| `--help-surface-2` | subtle fills (segment track, cards, hover) | secondary background |
| `--help-text` | main text | body text colour |
| `--help-muted` | secondary text | muted text colour |
| `--help-faint` | icons, hints, examples | faint/placeholder colour |
| `--help-border` | dividers | border colour |
| `--help-border-strong` | control borders | stronger border colour |
| `--help-accent` | active tab, focus, links, step numbers | brand/primary colour |
| `--help-accent-soft` | soft accent fill (step badges, NOW tag) | primary-tinted background |
| `--help-accent-line` | accent borders | primary-tinted border |
| `--help-radius` | panel cards, bubbles | standard radius |
| `--help-shadow` | bubble / panel shadow | elevation shadow |
| `--help-backdrop` | dim behind the panel | overlay colour |
| `--help-panel-width` | panel width | ~420–480px |
| `--help-space` | base spacing unit | spacing scale step (e.g. 4px / 0.25rem) |
| `--help-z` | panel layer | above sticky headers, below toasts |

Example mappings:
```css
/* CAR Management System (custom tokens) */
:root{
  --help-font: inherit;
  --help-surface: var(--bg);          --help-surface-2: var(--bg2);
  --help-text: var(--text);           --help-muted: var(--text2);   --help-faint: var(--text3);
  --help-border: var(--border);       --help-border-strong: var(--border2);
  --help-accent: var(--accent);       --help-accent-soft: var(--accent-bg); --help-accent-line: var(--accent-bd);
  --help-radius: 10px;                --help-shadow: 0 10px 28px rgba(0,0,0,.16);
  --help-backdrop: rgba(0,0,0,.35);   --help-panel-width: 460px;
  --help-space: 4px;                  --help-z: 55;
}

/* A Tailwind 4 system (theme values are CSS variables) */
:root{
  --help-font: var(--font-sans);
  --help-surface: var(--color-white);          --help-surface-2: var(--color-gray-50);
  --help-text: var(--color-gray-900);          --help-muted: var(--color-gray-600); --help-faint: var(--color-gray-400);
  --help-border: var(--color-gray-200);        --help-border-strong: var(--color-gray-300);
  --help-accent: var(--color-indigo-600);      --help-accent-soft: var(--color-indigo-50); --help-accent-line: var(--color-indigo-200);
  --help-radius: var(--radius-lg);             --help-shadow: var(--shadow-lg);
  --help-backdrop: rgb(0 0 0 / .35);           --help-panel-width: 460px;
  --help-space: var(--spacing);                --help-z: 50;
}
```
Dark mode: re-map the same variables inside the host's dark-mode selector. The components never hard-code colours.

---

## 4. Reference implementation (Laravel Blade + Alpine.js)

Requirements: Alpine.js (bundled with Livewire), an icon set (Font Awesome shown; swap the `<i>` classes for your icons).

### 4.1 Field label with tip — `resources/views/components/field-label.blade.php`
```blade
@props(['for' => null, 'tip' => null])

{{-- A form label with an optional info icon at its far right; hover, focus or tap it for a short tip.
     Extra controls for the label row (e.g. "Remove") go in the actions slot, before the icon. --}}
<div class="help-label">
    <label @if ($for) for="{{ $for }}" @endif>{{ $slot }}</label>
    <span class="help-label-end">
        {{ $actions ?? '' }}
        @if ($tip)
            <span class="help-tip" x-data="{ open: false }"
                  x-on:mouseenter="open = true" x-on:mouseleave="open = false" x-on:click.outside="open = false">
                <button type="button" class="help-tip-button" aria-label="About this field" :aria-expanded="open"
                        x-on:click="open = ! open" x-on:focus="open = true" x-on:blur="open = false">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                </button>
                <span class="help-tip-bubble" role="tooltip" x-show="open" x-cloak x-transition.opacity>{{ $tip }}</span>
            </span>
        @endif
    </span>
</div>
```
Usage:
```blade
<x-field-label for="complainant" tip="Who raised the problem — for example a customer, a distributor or internal audit.">Complainant</x-field-label>
<input id="complainant" wire:model="complainant">

{{-- with a label-row action --}}
<x-field-label tip="One or more sub-categories of this category.">
    Sub-category
    <x-slot:actions><button type="button" wire:click="removePair({{ $i }})">Remove</button></x-slot:actions>
</x-field-label>
```

### 4.2 Guide button — `resources/views/components/guide-button.blade.php`
```blade
@props(['name', 'label' => 'Guide'])

<button type="button" {{ $attributes->merge(['class' => 'btn btn-secondary help-guide-button']) }}
        x-data x-on:click="$dispatch('open-guide', { name: @js($name) })">
    <i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ $label }}
</button>
```
Use the host's own secondary-button classes in place of `btn btn-secondary`.

### 4.3 Guide panel — `resources/views/components/guide.blade.php`
```blade
@props(['name', 'title', 'subtitle' => null, 'stages' => null, 'currentStage' => null])

{{-- Side panel for one page or module's guide. Opened by <x-guide-button> with the same name.
     $stages (optional): ['stage-guide-name' => 'Label', ...] shows the stage switch;
     $currentStage marks the record's current stage "now". --}}
<div x-data="{ open: false }"
     x-on:open-guide.window="open = $event.detail.name === @js($name); if (open) { $nextTick(() => $refs.close.focus()) }"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak x-transition.opacity
     x-on:click.self="open = false"
     class="help-backdrop">
    <aside class="help-panel" role="dialog" aria-modal="true" aria-labelledby="guide-{{ $name }}-title">
        <div class="help-head">
            <div>
                <div class="help-title" id="guide-{{ $name }}-title">{{ $title }}</div>
                @if ($subtitle) <div class="help-subtitle">{{ $subtitle }}</div> @endif
            </div>
            <button type="button" class="help-close" x-ref="close" x-on:click="open = false" aria-label="Close the guide">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        @if ($stages)
            <div class="help-stages" role="tablist" aria-label="Stage">
                @foreach ($stages as $stage => $label)
                    <button type="button" role="tab" @class(['help-stage', 'is-active' => $stage === $name])
                            aria-selected="{{ $stage === $name ? 'true' : 'false' }}"
                            x-on:click="$dispatch('open-guide', { name: @js($stage) })">
                        {{ $label }}
                        @if ($stage === $currentStage) <span class="help-now">now</span> @endif
                    </button>
                @endforeach
            </div>
        @endif

        <div class="help-body">{{ $slot }}</div>
    </aside>
</div>
```

### 4.4 A guide's content — `resources/views/components/guides/<module>.blade.php`
```blade
<x-guide name="dashboard" title="Dashboard guide" subtitle="Monitoring records across sites.">
    <div x-data="{ tab: 'overview' }">
        <div class="help-tabs" role="tablist">
            <button type="button" role="tab" class="help-tab" :class="tab === 'overview' && 'is-active'"
                    :aria-selected="tab === 'overview'" x-on:click="tab = 'overview'">Overview</button>
            <button type="button" role="tab" class="help-tab" :class="tab === 'figures' && 'is-active'"
                    :aria-selected="tab === 'figures'" x-on:click="tab = 'figures'">Reading the figures</button>
        </div>

        <div x-show="tab === 'overview'" role="tabpanel">
            <ol class="help-flow">
                <li><span class="help-flow-num">1</span><div><strong>Pick a period</strong><span>It applies to records created in that period.</span></div></li>
                <li><span class="help-flow-num">2</span><div><strong>Narrow it down</strong><span>Filter by site and line. All figures follow the filters.</span></div></li>
                <li><span class="help-flow-num">3</span><div><strong>Act on what needs attention</strong><span>The table at the bottom lists overdue records.</span></div></li>
            </ol>
        </div>

        <div x-show="tab === 'figures'" x-cloak role="tabpanel">
            <dl class="help-list">
                <div class="help-item"><dt>Open</dt><dd>Not yet closed, as of today.</dd></div>
                <div class="help-item"><dt>Overdue</dt><dd>Open records past their deadline today.</dd></div>
            </dl>
        </div>
    </div>
</x-guide>
```
Comparison-card variant: add `help-cards` to the `<dl>` and an example line `<dd class="help-example">e.g. …</dd>`.

Catalogue variant (collapsed groups + search):
```blade
<div x-data="{ search: '' }">
    <input type="search" x-model="search" class="help-search" placeholder="Search…" aria-label="Search the list">
    @foreach ($groups as $group)
        <details class="help-group"
                 x-data="{ matches() { return [...$el.querySelectorAll('[data-search]')].some(i => i.dataset.search.includes(search.toLowerCase())) } }"
                 x-show="! search || matches()" x-effect="if (search) { $el.open = matches() }">
            <summary><i class="fa-solid fa-chevron-right help-chevron" aria-hidden="true"></i>
                <strong>{{ $group->name }}</strong><span class="help-meta">{{ $group->summary }}</span></summary>
            <dl class="help-list">
                @foreach ($group->items as $item)
                    <div class="help-item" data-search="{{ str($item->name.' '.$item->description)->lower() }}"
                         x-show="! search || $el.dataset.search.includes(search.toLowerCase())">
                        <dt>{{ $item->name }}</dt><dd>{{ $item->description }}</dd>
                    </div>
                @endforeach
            </dl>
        </details>
    @endforeach
</div>
```

### 4.5 Placing it on a page
```blade
<x-page-header title="Dashboard">
    <x-guide-button name="dashboard" />   {{-- first action on the right --}}
    {{-- …other header actions / filters… --}}
</x-page-header>

{{-- …page content… --}}

<x-guides.dashboard />                     {{-- once, at the end of the page --}}
```
Stage guides on a record page:
```blade
<x-guide-button :name="$currentStageGuide" />
<x-guides.stage-one :stages="$stageGuides" :current-stage="$currentStageGuide" />
<x-guides.stage-two :stages="$stageGuides" :current-stage="$currentStageGuide" />
```

### 4.6 Styles — themeable (`help.css` or inside the app stylesheet)
```css
[x-cloak]{ display:none !important; }

/* Field tip */
.help-label{ display:flex; justify-content:space-between; align-items:center; gap:calc(var(--help-space)*2); margin-bottom:calc(var(--help-space)*1.5); }
.help-label label{ margin-bottom:0; }
.help-label-end{ display:inline-flex; align-items:center; gap:calc(var(--help-space)*1.5); }
.help-tip{ position:relative; display:inline-flex; }
.help-tip-button{ border:0; background:transparent; padding:0 2px; line-height:1; cursor:help; color:var(--help-faint); font-size:.85em; }
.help-tip-button:hover, .help-tip-button:focus-visible{ color:var(--help-accent); outline:none; }
.help-tip-bubble{
  position:absolute; right:0; top:calc(100% + 6px); z-index:40; width:max-content; max-width:260px;
  padding:calc(var(--help-space)*2) calc(var(--help-space)*2.5); border-radius:var(--help-radius);
  font:400 .8rem/1.5 var(--help-font); text-transform:none; letter-spacing:0; color:var(--help-text);
  background:var(--help-surface); border:1px solid var(--help-border-strong); box-shadow:var(--help-shadow);
}

/* Guide button */
.help-guide-button{ flex-shrink:0; }

/* Panel */
.help-backdrop{ position:fixed; inset:0; z-index:var(--help-z); background:var(--help-backdrop); display:flex; justify-content:flex-end; }
.help-panel{
  width:100%; max-width:var(--help-panel-width); height:100%; display:flex; flex-direction:column;
  background:var(--help-surface); color:var(--help-text); font-family:var(--help-font);
  border-left:1px solid var(--help-border-strong); box-shadow:var(--help-shadow);
}
.help-head{ display:flex; justify-content:space-between; align-items:flex-start; gap:calc(var(--help-space)*2.5); padding:calc(var(--help-space)*4) calc(var(--help-space)*4.5) calc(var(--help-space)*2.5); }
.help-title{ font-weight:700; font-size:1.05rem; }
.help-subtitle{ color:var(--help-muted); font-size:.85rem; margin-top:2px; line-height:1.5; }
.help-close{ border:0; background:transparent; color:var(--help-muted); cursor:pointer; padding:4px 8px; border-radius:var(--help-radius); }
.help-close:hover, .help-close:focus-visible{ color:var(--help-text); background:var(--help-surface-2); outline:none; }
.help-body{ flex:1; overflow:auto; padding:0 calc(var(--help-space)*4.5) calc(var(--help-space)*4.5); }

/* Stage switch (segmented) */
.help-stages{ display:flex; gap:3px; padding:3px; margin:0 calc(var(--help-space)*4.5) var(--help-space); border-radius:var(--help-radius); background:var(--help-surface-2); border:1px solid var(--help-border); }
.help-stage{ flex:1; display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:6px 8px; border:0; border-radius:calc(var(--help-radius) - 3px); background:transparent; color:var(--help-muted); font:600 .85rem var(--help-font); cursor:pointer; }
.help-stage.is-active{ background:var(--help-surface); color:var(--help-text); box-shadow:0 1px 3px rgba(0,0,0,.12); }
.help-now{ font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:1px 6px; border-radius:999px; background:var(--help-accent-soft); color:var(--help-accent); border:1px solid var(--help-accent-line); }

/* Topic tabs (underlined) */
.help-tabs{ display:flex; gap:calc(var(--help-space)*4.5); margin:calc(var(--help-space)*3) 0 calc(var(--help-space)*3.5); border-bottom:1px solid var(--help-border); }
.help-tab{ border:0; border-bottom:2px solid transparent; background:transparent; padding:6px 0 8px; margin-bottom:-1px; color:var(--help-muted); font:600 .85rem var(--help-font); cursor:pointer; }
.help-tab:hover{ color:var(--help-text); }
.help-tab.is-active{ color:var(--help-accent); border-bottom-color:var(--help-accent); }

/* Overview flow */
.help-flow{ list-style:none; margin:0 0 calc(var(--help-space)*3.5); padding:0; display:flex; flex-direction:column; gap:calc(var(--help-space)*3); }
.help-flow li{ display:flex; gap:calc(var(--help-space)*2.5); align-items:flex-start; }
.help-flow strong{ display:block; font-size:.9rem; }
.help-flow span:not(.help-flow-num){ display:block; color:var(--help-muted); font-size:.85rem; line-height:1.5; margin-top:2px; }
.help-flow-num{ flex-shrink:0; width:22px; height:22px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; background:var(--help-accent-soft); color:var(--help-accent); border:1px solid var(--help-accent-line); }
.help-text{ margin:0; color:var(--help-muted); font-size:.85rem; line-height:1.55; }

/* Reference: definitions, cards, catalogue */
.help-list{ margin:0; display:flex; flex-direction:column; gap:calc(var(--help-space)*2); }
.help-item dt{ font-weight:600; font-size:.875rem; }
.help-item dd{ margin:2px 0 0; color:var(--help-muted); font-size:.85rem; line-height:1.5; }
.help-cards .help-item{ padding:calc(var(--help-space)*2.5) calc(var(--help-space)*3); border-radius:var(--help-radius); background:var(--help-surface-2); border:1px solid var(--help-border); }
.help-example{ font-style:italic; color:var(--help-faint) !important; margin-top:4px !important; }
.help-search{ width:100%; padding:7px 10px; border-radius:var(--help-radius); border:1px solid var(--help-border-strong); background:var(--help-surface); color:var(--help-text); font:inherit; margin-bottom:var(--help-space); }
.help-group{ border-bottom:1px solid var(--help-border); }
.help-group:last-child{ border-bottom:0; }
.help-group > summary{ list-style:none; cursor:pointer; padding:11px 0; display:flex; align-items:baseline; flex-wrap:wrap; gap:4px 10px; }
.help-group > summary::-webkit-details-marker{ display:none; }
.help-meta{ margin-left:auto; color:var(--help-faint); font-size:.75rem; }
.help-chevron{ color:var(--help-faint); font-size:.6rem; transition:transform .15s; }
.help-group[open] .help-chevron{ transform:rotate(90deg); }
.help-group[open] > .help-list{ padding:0 0 12px 16px; }

@media (max-width:640px){ .help-panel{ max-width:100%; } }
```

> In the CAR system the same rules exist under the names `field-label`, `field-tip*`, `guide*`. Use the `help-*` names above for new systems.

---

## 5. Accessibility checklist
- [ ] Tip trigger is a real `<button type="button">` with `aria-label="About this field"` and `aria-expanded`.
- [ ] Bubble has `role="tooltip"`; reachable by keyboard (focus opens it, blur closes it).
- [ ] Guide panel has `role="dialog"`, `aria-modal="true"`, `aria-labelledby` pointing at its title.
- [ ] Focus moves to the close button on open; **Esc** closes.
- [ ] Tabs use `role="tablist"` / `role="tab"` / `aria-selected`; panels use `role="tabpanel"`.
- [ ] Icons are decorative (`aria-hidden="true"`); every icon-only button has an `aria-label`.
- [ ] Colour is never the only signal (active tab is also underlined; "now" is a word, not just a colour).
- [ ] Works at phone width: panel goes full width, bubbles stay inside the screen (max-width 260px, right-aligned).

---

## 6. Testing checklist (Pest examples)
```php
it('puts a short tip at the right of each field label', function () {
    Livewire::test(CreateForm::class)
        ->assertSeeInOrder(['Complainant', 'Who raised the problem', 'Details of the problem', 'Be specific']);
});

it('has a two-tab guide opened from the page header', function () {
    Livewire::test(Dashboard::class)
        ->assertSeeHtml('open-guide')
        ->assertSeeInOrder(['Guide', 'Dashboard guide', 'Overview', 'Reading the figures', 'Pick a period']);
});

it('opens the guide for the record\'s current stage', function () {
    Livewire::test(RecordPage::class, ['record' => $recordInStageTwo])
        ->assertViewHas('currentStageGuide', 'stage-two');
});
```
Gotchas learned the hard way:
- After a Livewire update, assertions run against the JSON response, where `—`, `·` and `/` may be escaped. Assert guide text on the **first render**, or avoid those characters in assertions.
- A guide's own wording (e.g. "What to report under each…") can collide with `assertDontSee` checks — assert the most specific string.
- Global element selectors in the host stylesheet (e.g. `nav { flex: 1 }`) leak into the panel. Use `div`s for the panel's internals, or scope the host's rule.

---

## 7. Adoption checklist for a new system
1. Copy the three components (field label, guide button, guide) and the CSS.
2. Map the `--help-*` variables to the system's tokens (light and dark).
3. Swap icon classes if the system doesn't use Font Awesome.
4. Pick the pages that get a guide: every form, every record page with stages, every dashboard/report, admin screens with non-obvious rules.
5. For each form: replace labels with the field-label component; write tips (≤ 30 words); delete static hint lines.
6. For each guided page: write `guides/<page>.blade.php` — Overview (≤ 3 steps) + at most one reference tab; add the Guide button as the first header action; place the guide once on the page.
7. Add the tests from section 6.

**Do**
- One Guide button per page, top right, first.
- Overview first; at most two tabs.
- Tips for non-obvious fields only.

**Don't**
- Stack descriptions under fields.
- Put field instructions in the guide, or process explanations in tips.
- Hard-code colours, fonts or spacing in the components.
- Add a guide button inside cards/sections.

---

## 8. Instructions to give an AI assistant in another project
> Apply the "Module Guides & Field Tips" pattern from this document. Keep this system's theme: map the `--help-*` CSS variables to the existing design tokens (fonts, colours, spacing, radii, dark mode) rather than introducing new ones. Add the field-label, guide-button and guide components; give every non-obvious form field a tip of at most 30 words at the far right of its label and remove static hint lines; give each page I list a guide opened from a "Guide" button placed first on the right of the page header, with an Overview tab (at most 3 steps) and at most one reference tab. Follow the accessibility and testing checklists. Show me the list of fields and tips, and the guide outline, before writing the guide text.

---

## 9. Other front-end stacks
The behaviour is plain events and state, so it ports directly:
- **Opening a guide**: a global event (`open-guide` with `{ name }`). Each panel listens and sets `open = (detail.name === myName)`, which also closes the others. In React/Vue use a small store/context (`openGuide: string | null`) instead of window events.
- **Tip**: local `open` state toggled by hover, focus and click; closed by blur and outside click. Use the framework's popover/floating helper if it has one, keeping the placement (below, right-aligned).
- **Tabs**: local `tab` state; reset to the first tab when the panel opens.
- **Catalogue search**: lowercase "name + description" on each item; show items that contain the query; open groups that have a match.
- **Livewire specifics**: tips and panels are pure Alpine — no server round trips. Live values in tips (dates, limits) are rendered by the server on each render.
