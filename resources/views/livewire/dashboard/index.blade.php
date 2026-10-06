<section>
    <x-page-header title="Dashboard" subtitle="Repeat offenses, response & resolution time, open/closed status and frequency of CAR issuance">
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <div class="field">
                <select wire:model.live="period" aria-label="Period" style="min-height:30px; padding:4px 8px;">
                    @foreach ($periods as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <select wire:model.live="line" aria-label="Business line" style="min-height:30px; padding:4px 8px;">
                    <option value="">All business lines</option>
                    @foreach ($lines as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <select wire:model.live="farm" aria-label="Farm" style="min-height:30px; padding:4px 8px;">
                    <option value="">All farms</option>
                    @foreach ($farms as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-page-header>

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
                <span class="delta" style="background:var(--red-bg);color:var(--red)">{{ $stats['overdue'] ? 'needs attention' : 'none' }}</span>
            </div>
            <div class="card stat-tile">
                <div class="num tnum">{{ $stats['avg_response_days'] ?? '—' }}<span style="font-size:13px;color:var(--text3)"> d</span></div>
                <div class="lbl">Avg. response time (issue → response submitted)</div>
                <span class="delta" style="background:var(--green-bg);color:var(--green)">{{ $stats['on_time_response_rate'] !== null ? $stats['on_time_response_rate'].'% on time' : 'no responses yet' }}</span>
            </div>
            <div class="card stat-tile">
                <div class="num tnum">{{ $stats['avg_resolution_days'] ?? '—' }}<span style="font-size:13px;color:var(--text3)"> d</span></div>
                <div class="lbl">Avg. resolution time (issue → closed)</div>
                <span class="delta" style="background:var(--amber-bg);color:var(--amber)">{{ $stats['closed'] }} closed of {{ $stats['issued'] }} issued</span>
            </div>
        </div>

        <div class="detail-grid" style="margin-top:14px;">
            <div class="card" style="padding:16px 18px;">
                <div class="section-title">CARs issued, last 6 months</div>
                <div class="bar-chart">
                    @foreach ($stats['months'] as $month => $count)
                        <div class="bar-col" wire:key="month-{{ $month }}">
                            <div class="bar-num tnum">{{ $count }}</div>
                            <div class="bar" style="height:{{ max(2, round($count / $maxMonth * 100)) }}%"></div>
                            <div class="bar-lbl">{{ $month }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card" style="padding:16px 18px;">
                <div class="section-title">Repeat offenses by category / sub-category — {{ strtolower($periods[$period]) }}</div>
                <div style="display:flex; flex-direction:column; gap:9px; margin-top:4px;">
                    @forelse ($stats['repeat_offenses'] as $offense)
                        <div wire:key="offense-{{ $loop->index }}">
                            <div style="display:flex; justify-content:space-between; font-size:11.5px; margin-bottom:4px;">
                                <span>{{ $offense['subcategory'] }} <span style="color:var(--text3)">— {{ $offense['category'] }} · {{ $offense['line'] }}</span></span>
                                <span class="tnum" style="font-weight:600;">{{ $offense['count'] }}</span>
                            </div>
                            <div style="height:6px; border-radius:4px; background:var(--bg2); overflow:hidden;">
                                <div style="height:100%; width:{{ round($offense['count'] / $maxOffense * 100) }}%; background:var(--red); opacity:.7;"></div>
                            </div>
                        </div>
                    @empty
                        <div style="color:var(--text3); font-size:11.5px;">No sub-category was issued more than once in this period.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="detail-grid" style="margin-top:14px;">
            <div class="card" style="padding:16px 18px;">
                <div class="section-title">Status of CARs issued — {{ strtolower($periods[$period]) }}</div>
                <div class="field-grid" style="grid-template-columns:repeat(3,1fr);">
                    <div class="kv"><span class="k">Issued</span><span class="v tnum" style="font-size:20px;">{{ $stats['issued'] }}</span></div>
                    <div class="kv"><span class="k">Closed</span><span class="v tnum" style="font-size:20px; color:var(--green);">{{ $stats['closed'] }}</span></div>
                    <div class="kv"><span class="k">Still open</span><span class="v tnum" style="font-size:20px;">{{ $stats['issued'] - $stats['closed'] - $stats['voided'] }}</span></div>
                </div>
            </div>
            <div class="card" style="padding:16px 18px;">
                <div class="section-title">Issued by unit — {{ strtolower($periods[$period]) }}</div>
                <div style="display:flex; flex-direction:column; gap:7px;">
                    @forelse ($stats['by_unit'] as $unit)
                        <div style="display:flex; align-items:center; gap:10px; font-size:11.5px;" wire:key="unit-{{ $loop->index }}">
                            <span style="width:130px; flex-shrink:0;">{{ $unit['unit'] }}</span>
                            <div style="flex:1; height:6px; border-radius:4px; background:var(--bg2); overflow:hidden;">
                                <div style="height:100%; width:{{ round($unit['count'] / $maxUnit * 100) }}%; background:var(--accent); opacity:.75;"></div>
                            </div>
                            <span class="tnum" style="width:24px; text-align:right; font-weight:600;">{{ $unit['count'] }}</span>
                        </div>
                    @empty
                        <div style="color:var(--text3); font-size:11.5px;">No CARs issued in this period.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:14px;">
            <div style="padding:14px 18px 4px;"><div class="section-title" style="margin:0;">Needs attention — overdue now</div></div>
            <div class="table-scroll">
                <x-car-table :cars="$attention" empty="Nothing overdue." />
            </div>
        </div>
    </div>
</section>
