{{--
    The Period comparison guide: how to set up a comparison, and how to read the change column.
--}}
<x-guide name="compare" title="Period comparison guide" subtitle="Two periods side by side — for reviews and reports.">
    <div x-data="{ tab: 'overview' }">
        <div class="guide-tabs guide-tabs-main" role="tablist">
            <button type="button" role="tab" class="guide-tab" :class="tab === 'overview' && 'is-active'" :aria-selected="tab === 'overview'" x-on:click="tab = 'overview'">Overview</button>
            <button type="button" role="tab" class="guide-tab" :class="tab === 'reading' && 'is-active'" :aria-selected="tab === 'reading'" x-on:click="tab = 'reading'">Reading the comparison</button>
        </div>

        <div x-show="tab === 'overview'" role="tabpanel">
            <ol class="guide-flow">
                <li>
                    <span class="guide-flow-num">1</span>
                    <div><strong>Pick a period on each side</strong><span>A full year, a half, a quarter, one month, or your own dates — e.g. Q1 2025 against Q1 2026. "Consecutive years" moves the other year with the one you pick; Clear starts over on last year vs this year.</span></div>
                </li>
                <li>
                    <span class="guide-flow-num">2</span>
                    <div><strong>Filter</strong><span>Business line and farm apply to both sides; untick "Same filters on both sides" to compare, say, PFC with BROOKDALE.</span></div>
                </li>
                <li>
                    <span class="guide-flow-num">3</span>
                    <div><strong>Print or export</strong><span>Print gives a clean landscape page stating what was compared; Export CSV gives the same figures for a spreadsheet. The page link keeps your choices.</span></div>
                </li>
            </ol>
            <p class="guide-text">A CAR counts in the period it was issued, the same as on the Dashboard.</p>
        </div>

        <div x-show="tab === 'reading'" x-cloak role="tabpanel">
            <dl class="guide-list">
                <div class="guide-item"><dt>Change column</dt><dd>From the left period to the right one. Green is an improvement, red is worse — fewer CARs and faster response are better, so a drop can be green.</dd></div>
                <div class="guide-item"><dt>Same span</dt><dd>When a period isn't over (like this year), both sides are cut to the same number of days, so a part-year is never compared with a whole one.</dd></div>
                <div class="guide-item"><dt>Closure rate</dt><dd>Closed CARs as a share of those issued in the period (voided ones left out).</dd></div>
                <div class="guide-item"><dt>Still open</dt><dd>CARs issued in the period that are not closed or voided today.</dd></div>
                <div class="guide-item"><dt>Response and resolution</dt><dd>Days from issue to the first response, the share on time, and days from issue to closure.</dd></div>
                <div class="guide-item"><dt>Per month</dt><dd>Both periods on one chart, month by month in order — the 1st month of each side side by side.</dd></div>
                <div class="guide-item"><dt>Breakdowns</dt><dd>Sub-category (top 10), category, unit and farm. A CAR counts under each of its sub-categories and units.</dd></div>
            </dl>
        </div>
    </div>
</x-guide>
