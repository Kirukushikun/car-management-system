@props(['lines', 'current' => null, 'currentPhase' => null])

{{--
    The Phase I guide, in two short tabs: how the phase moves, and the category reference.
    Field-level help lives next to each field (the info icon on its label), not here.
--}}
<x-guide name="phase-one" :current-phase="$currentPhase" title="Phase I guide" subtitle="Initiation & Submission — filing a CAR.">
    <div x-data="{ tab: 'overview' }">
        <div class="guide-tabs guide-tabs-main" role="tablist">
            <button type="button" role="tab" class="guide-tab" :class="tab === 'overview' && 'is-active'" :aria-selected="tab === 'overview'" x-on:click="tab = 'overview'">Overview</button>
            <button type="button" role="tab" class="guide-tab" :class="tab === 'categories' && 'is-active'" :aria-selected="tab === 'categories'" x-on:click="tab = 'categories'">Categories</button>
        </div>

        <div x-show="tab === 'overview'" role="tabpanel">
            <ol class="guide-flow">
                <li>
                    <span class="guide-flow-num">1</span>
                    <div><strong>You file the CAR</strong><span>The reference number and deadlines are set when you submit.</span></div>
                </li>
                <li>
                    <span class="guide-flow-num">2</span>
                    <div><strong>The Complainant Approver reviews it</strong><span>They approve it, or return it with a reason.</span></div>
                </li>
                <li>
                    <span class="guide-flow-num">3</span>
                    <div><strong>Released to the farm — or back to you</strong><span>Approved CARs go to the farm's Concerned Department (Phase II). Returned ones wait in your queue to correct and resubmit.</span></div>
                </li>
            </ol>
            <p class="guide-text">Not sure about a field? Use the <x-icon name="info" /> at the right of its label.</p>
        </div>

        <div x-show="tab === 'categories'" x-cloak role="tabpanel">
            <x-guides.category-reference :lines="$lines" :current="$current" />
        </div>
    </div>
</x-guide>
