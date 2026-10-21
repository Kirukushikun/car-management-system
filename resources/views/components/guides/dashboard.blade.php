{{--
    The Dashboard guide, in two short tabs: what the page is for and how the filters work, and what
    each figure counts.
--}}
<x-guide name="dashboard" title="Dashboard guide" subtitle="Monitoring CARs across farms and business lines.">
    <div x-data="{ tab: 'overview' }">
        <div class="guide-tabs guide-tabs-main" role="tablist">
            <button type="button" role="tab" class="guide-tab" :class="tab === 'overview' && 'is-active'" :aria-selected="tab === 'overview'" x-on:click="tab = 'overview'">Overview</button>
            <button type="button" role="tab" class="guide-tab" :class="tab === 'figures' && 'is-active'" :aria-selected="tab === 'figures'" x-on:click="tab = 'figures'">Reading the figures</button>
        </div>

        <div x-show="tab === 'overview'" role="tabpanel">
            <p class="guide-text">A read-only view of every CAR: what is open or overdue right now, how fast farms respond and resolve, and which problems keep coming back.</p>
            <ol class="guide-flow" style="margin-top:14px;">
                <li>
                    <span class="guide-flow-num">1</span>
                    <div><strong>Pick a period</strong><span>Last 30 days to all time. It applies to CARs <em>issued</em> in that period.</span></div>
                </li>
                <li>
                    <span class="guide-flow-num">2</span>
                    <div><strong>Narrow it down</strong><span>Filter by business line (TABLE EGG or DOP) and by farm. All figures follow these filters.</span></div>
                </li>
                <li>
                    <span class="guide-flow-num">3</span>
                    <div><strong>Act on what needs attention</strong><span>The table at the bottom lists overdue CARs — open one to see who it is waiting on.</span></div>
                </li>
            </ol>
            <p class="guide-text"><strong>Open</strong> and <strong>Overdue</strong> always show today's position; the rest covers the chosen period.</p>
            <p class="guide-text" style="margin-top:8px;">To put two years, halves, quarters or months side by side, use <strong>Compare periods</strong>.</p>
        </div>

        <div x-show="tab === 'figures'" x-cloak role="tabpanel">
            <dl class="guide-list">
                <div class="guide-item"><dt>Open CARs</dt><dd>Not yet closed or voided, as of today. "In Phase I" are still being filed or reviewed before release.</dd></div>
                <div class="guide-item"><dt>Overdue</dt><dd>Open CARs past their deadline today — the response deadline until the farm first responds, then the implementation (or revised) deadline.</dd></div>
                <div class="guide-item"><dt>Avg. response time</dt><dd>Days from issue to the farm's first submitted response, and the share submitted by the response deadline.</dd></div>
                <div class="guide-item"><dt>Avg. resolution time</dt><dd>Days from issue to closure, for CARs issued in the period that are now closed.</dd></div>
                <div class="guide-item"><dt>CARs issued, last 6 months</dt><dd>How many CARs were issued each month — the frequency of issuance.</dd></div>
                <div class="guide-item"><dt>Repeat offenses</dt><dd>Sub-categories issued more than once in the period, most frequent first. A CAR with several sub-categories counts under each.</dd></div>
                <div class="guide-item"><dt>Status of CARs issued</dt><dd>Of the CARs issued in the period: how many are closed and how many are still open.</dd></div>
                <div class="guide-item"><dt>Issued by unit</dt><dd>Which operational units CARs were issued to. A CAR issued to several units counts under each.</dd></div>
            </dl>
        </div>
    </div>
</x-guide>
