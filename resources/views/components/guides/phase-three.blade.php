@props(['currentPhase' => null])

{{--
    The Phase III guide, in two short tabs: how the phase moves, and what good evidence and the two
    checks look like. Field-level help lives next to each field (the info icon on its label).
--}}
<x-guide name="phase-three" :current-phase="$currentPhase" title="Phase III guide" subtitle="Implementation, Verification & Closure — proving the fix and closing the CAR.">
    <div x-data="{ tab: 'overview' }">
        <div class="guide-tabs guide-tabs-main" role="tablist">
            <button type="button" role="tab" class="guide-tab" :class="tab === 'overview' && 'is-active'" :aria-selected="tab === 'overview'" x-on:click="tab = 'overview'">Overview</button>
            <button type="button" role="tab" class="guide-tab" :class="tab === 'checks' && 'is-active'" :aria-selected="tab === 'checks'" x-on:click="tab = 'checks'">Evidence &amp; checks</button>
        </div>

        <div x-show="tab === 'overview'" role="tabpanel">
            <ol class="guide-flow">
                <li>
                    <span class="guide-flow-num">1</span>
                    <div><strong>The Concerned Department uploads the evidence</strong><span>Proof the corrective actions were carried out, by the implementation deadline (Step 11).</span></div>
                </li>
                <li>
                    <span class="guide-flow-num">2</span>
                    <div><strong>The Concerned Department Approver checks it worked</strong><span>Effective goes on to final acceptance; not effective sends it back to Phase II for a new solution (Step 12).</span></div>
                </li>
                <li>
                    <span class="guide-flow-num">3</span>
                    <div><strong>The Complainant Approver gives final acceptance</strong><span>Accept closes the CAR. Not accepted needs a reason and a new end date, and the Concerned Department proposes a new solution (Step 13).</span></div>
                </li>
            </ol>
            <p class="guide-text">Each attempt is numbered — Solution 1, 2, 3 — and earlier ones stay on the CAR with the reason they ended.</p>
        </div>

        <div x-show="tab === 'checks'" x-cloak role="tabpanel">
            <dl class="guide-list guide-where">
                <div class="guide-item">
                    <dt>Good evidence</dt>
                    <dd>Shows each corrective action in place: photos, checklists, logs, training records or the updated procedure — dated where possible.</dd>
                    <dd class="guide-example">e.g. Photo of the new cold-storage rack and a week of the temperature log.</dd>
                </div>
                <div class="guide-item">
                    <dt>Effectiveness check — did it work?</dt>
                    <dd>Not just "was it done", but "has the problem stopped". Look at the results since the fix.</dd>
                    <dd class="guide-example">e.g. No spoiled-egg complaints in the two weeks since.</dd>
                </div>
                <div class="guide-item">
                    <dt>Final acceptance — is the customer satisfied?</dt>
                    <dd>Accept to close, with optional remarks. If not, say what is still wrong and set a realistic new end date.</dd>
                    <dd class="guide-example">e.g. Not accepted — the Lucena outlet still reports cracked eggs.</dd>
                </div>
            </dl>
        </div>
    </div>
</x-guide>
