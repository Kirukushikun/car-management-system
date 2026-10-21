<section>
    <x-page-header title="Dashboard" subtitle="Repeat offenses, response & resolution time, open/closed status and frequency of CAR issuance">
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <x-guide-button name="dashboard" />
            <a href="{{ route('dashboard.compare') }}" wire:navigate class="btn btn-secondary" style="text-decoration:none;"><x-icon name="compare" /> Compare periods</a>
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
            @php
                $months = $stats['months'];
                $monthTotal = array_sum($months);
                $busiest = $monthTotal > 0 ? array_search(max($months), $months, true) : null;
                $thisMonth = end($months);
                $lastMonth = prev($months);
            @endphp
            <div class="card dash-card">
                <div class="section-title">CARs issued, last 6 months</div>
                <div class="month-chart">
                    <div class="month-guides" aria-hidden="true"><span></span><span></span><span></span></div>
                    @foreach ($months as $month => $count)
                        <div class="month-col" wire:key="month-{{ $month }}" title="{{ $month }}: {{ $count }} {{ str('CAR')->plural($count) }}">
                            <div class="month-num tnum">{{ $count }}</div>
                            <div class="month-track">
                                <div @class(['month-bar', 'is-current' => $loop->last]) style="height:{{ max(2, round($count / $maxMonth * 100)) }}%"></div>
                            </div>
                            <div class="month-lbl">{{ $month }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="dash-summary">
                    <div><span class="k">Total</span><span class="v tnum">{{ $monthTotal }}</span></div>
                    <div><span class="k">Monthly average</span><span class="v tnum">{{ round($monthTotal / max(1, count($months)), 1) }}</span></div>
                    <div><span class="k">Busiest month</span><span class="v">{{ $busiest ? $busiest.' · '.$months[$busiest] : '—' }}</span></div>
                    <div>
                        <span class="k">This month so far</span>
                        <span class="v tnum">{{ $thisMonth }}
                            @if ($thisMonth !== $lastMonth)
                                <span @class(['dash-delta', 'is-up' => $thisMonth > $lastMonth, 'is-down' => $thisMonth < $lastMonth])>{{ $thisMonth > $lastMonth ? '▲' : '▼' }} {{ abs($thisMonth - $lastMonth) }} vs last month</span>
                            @endif
                        </span>
                    </div>
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
            @php
                $stillOpen = $stats['issued'] - $stats['closed'] - $stats['voided'];
                $share = fn (int $n): float => $stats['issued'] > 0 ? round($n / $stats['issued'] * 100, 1) : 0;
            @endphp
            <div class="card dash-card">
                <div class="section-title">Status of CARs issued — {{ strtolower($periods[$period]) }}</div>
                <div class="field-grid" style="grid-template-columns:repeat(4,1fr);">
                    <div class="kv"><span class="k">Issued</span><span class="v tnum" style="font-size:20px;">{{ $stats['issued'] }}</span></div>
                    <div class="kv"><span class="k">Closed</span><span class="v tnum" style="font-size:20px; color:var(--green);">{{ $stats['closed'] }}</span></div>
                    <div class="kv"><span class="k">Still open</span><span class="v tnum" style="font-size:20px;">{{ $stillOpen }}</span></div>
                    <div class="kv"><span class="k">Voided</span><span class="v tnum" style="font-size:20px; color:var(--text3);">{{ $stats['voided'] }}</span></div>
                </div>

                <div class="status-split" role="img" aria-label="{{ $share($stats['closed']) }}% closed, {{ $share($stillOpen) }}% open, {{ $share($stats['voided']) }}% voided">
                    <span class="is-closed" style="width:{{ $share($stats['closed']) }}%"></span>
                    <span class="is-open" style="width:{{ $share($stillOpen) }}%"></span>
                    <span class="is-voided" style="width:{{ $share($stats['voided']) }}%"></span>
                </div>
                <div class="status-legend">
                    <span><i class="is-closed"></i>Closed {{ round($share($stats['closed'])) }}%</span>
                    <span><i class="is-open"></i>Open {{ round($share($stillOpen)) }}%</span>
                    <span><i class="is-voided"></i>Voided {{ round($share($stats['voided'])) }}%</span>
                </div>

                <div class="status-phases">
                    <div class="status-phases-title">Where the open ones are</div>
                    @foreach ([1 => 'Phase I — being filed or reviewed', 2 => 'Phase II — response and approval', 3 => 'Phase III — implementation and closure'] as $phase => $label)
                        <div class="status-phase" wire:key="open-phase-{{ $phase }}">
                            <span>{{ $label }}</span>
                            <span class="tnum">{{ $stats['issued_open_by_phase'][$phase] ?? 0 }}</span>
                        </div>
                    @endforeach
                    <div @class(['status-phase', 'is-overdue' => $stats['issued_overdue'] > 0])>
                        <span>Overdue among them</span>
                        <span class="tnum">{{ $stats['issued_overdue'] }}</span>
                    </div>
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
            <x-table-footer :paginator="$attention" label="overdue CARs" />
        </div>
    </div>

    <x-guides.dashboard />
</section>
