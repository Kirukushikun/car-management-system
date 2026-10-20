@props(['currentPhase' => null])

{{--
    The Phase II guide, in two short tabs: how the phase moves, and what belongs in containment,
    root cause and corrective action. Field-level help lives next to each field (the info icon on
    its label), not here.
--}}
<x-guide name="phase-two" :current-phase="$currentPhase" title="Phase II guide" subtitle="Response, Root Cause & Action Planning — answering a CAR.">
    <div x-data="{ tab: 'overview' }">
        <div class="guide-tabs guide-tabs-main" role="tablist">
            <button type="button" role="tab" class="guide-tab" :class="tab === 'overview' && 'is-active'" :aria-selected="tab === 'overview'" x-on:click="tab = 'overview'">Overview</button>
            <button type="button" role="tab" class="guide-tab" :class="tab === 'where' && 'is-active'" :aria-selected="tab === 'where'" x-on:click="tab = 'where'">What goes where</button>
        </div>

        <div x-show="tab === 'overview'" role="tabpanel">
            <ol class="guide-flow">
                <li>
                    <span class="guide-flow-num">1</span>
                    <div><strong>The Concerned Department writes the response</strong><span>Containment, root cause and corrective actions (Steps 7–9). Save a draft any time; submit by the response deadline.</span></div>
                </li>
                <li>
                    <span class="guide-flow-num">2</span>
                    <div><strong>The Concerned Department Approver reviews it</strong><span>They approve it, which starts Phase III, or return it with a reason to revise and resubmit.</span></div>
                </li>
                <li>
                    <span class="guide-flow-num">3</span>
                    <div><strong>If the fix later fails, it comes back here</strong><span>"Not effective" or "not accepted" opens a new solution, starting from the last one.</span></div>
                </li>
            </ol>
            <p class="guide-text">Not sure about a field? Use the <x-icon name="info" /> at the right of its label.</p>
        </div>

        <div x-show="tab === 'where'" x-cloak role="tabpanel">
            <dl class="guide-list guide-where">
                <div class="guide-item">
                    <dt>Containment — stop it now</dt>
                    <dd>Quick actions so the problem goes no further while you investigate.</dd>
                    <dd class="guide-example">e.g. Replaced the spoiled trays and held the rest of the batch.</dd>
                </div>
                <div class="guide-item">
                    <dt>Root cause — why it happened</dt>
                    <dd>The underlying cause, not the symptom. Keep asking "why?" until you reach something you can fix.</dd>
                    <dd class="guide-example">e.g. Dirty eggs sat three days at room temperature before dispatch.</dd>
                </div>
                <div class="guide-item">
                    <dt>Corrective action — stop it coming back</dt>
                    <dd>A lasting fix aimed at the root cause, with an owner and dates. Add one row per action.</dd>
                    <dd class="guide-example">e.g. Move dirty eggs to cold storage on the day of collection.</dd>
                </div>
            </dl>
        </div>
    </div>
</x-guide>
