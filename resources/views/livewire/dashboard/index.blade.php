<section>
    <x-page-header title="Dashboard" subtitle="Repeat offenses, response & resolution time, open/closed status — Sept 1–17, 2026 (sample data)" />

    <div class="content">
        <div class="stat-grid">
            <div class="card stat-tile">
                <div class="num tnum">{{ $stats['open'] }}</div>
                <div class="lbl">Open CARs</div>
                <span class="delta" style="background:var(--blue-bg);color:var(--blue)">{{ $stats['phase_one'] }} in Phase I</span>
            </div>
            <div class="card stat-tile">
                <div class="num tnum" style="color:var(--red)">{{ $stats['overdue'] }}</div>
                <div class="lbl">Overdue (response or implementation)</div>
                <span class="delta" style="background:var(--red-bg);color:var(--red)">needs attention</span>
            </div>
            <div class="card stat-tile">
                <div class="num tnum">{{ $stats['avg_response_days'] }}<span style="font-size:13px;color:var(--text3)"> d</span></div>
                <div class="lbl">Avg. response time</div>
                <span class="delta" style="background:var(--green-bg);color:var(--green)">within target</span>
            </div>
            <div class="card stat-tile">
                <div class="num tnum">{{ $stats['avg_resolution_days'] }}<span style="font-size:13px;color:var(--text3)"> d</span></div>
                <div class="lbl">Avg. resolution time</div>
                <span class="delta" style="background:var(--amber-bg);color:var(--amber)">+0.6d vs Aug</span>
            </div>
        </div>

        <div class="detail-grid" style="margin-top:14px;">
            <div class="card" style="padding:16px 18px;">
                <div class="section-title">CARs issued, last 6 months</div>
                <div class="bar-chart">
                    @foreach ($stats['months'] as $month => $count)
                        <div class="bar-col" wire:key="month-{{ $month }}">
                            <div class="bar-num tnum">{{ $count }}</div>
                            <div class="bar" style="height:{{ round($count / $maxMonth * 100) }}%"></div>
                            <div class="bar-lbl">{{ $month }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card" style="padding:16px 18px;">
                <div class="section-title">Repeat offenses by category / sub-category</div>
                <div style="display:flex; flex-direction:column; gap:9px; margin-top:4px;">
                    @foreach ($stats['repeat_offenders'] as $offender)
                        <div wire:key="offender-{{ $loop->index }}">
                            <div style="display:flex; justify-content:space-between; font-size:11.5px; margin-bottom:4px;">
                                <span>{{ $offender['subcategory'] }} <span style="color:var(--text3)">— {{ $offender['category'] }}</span></span>
                                <span class="tnum" style="font-weight:600;">{{ $offender['count'] }}</span>
                            </div>
                            <div style="height:6px; border-radius:4px; background:var(--bg2); overflow:hidden;">
                                <div style="height:100%; width:{{ round($offender['count'] / $maxOffender * 100) }}%; background:var(--accent); opacity:.75;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:14px;">
            <div style="padding:14px 18px 4px;"><div class="section-title" style="margin:0;">Needs attention</div></div>
            <div class="table-scroll">
                <x-car-table :cars="$attention" empty="Nothing overdue." />
            </div>
        </div>

        <footer class="note">Scaffold dataset — the stat tiles count the sample CARs; time averages, the monthly chart and repeat offenses are fixed values until Phase 7.</footer>
    </div>
</section>
