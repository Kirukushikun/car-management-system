<section class="compare-page">
    <x-page-header title="Period comparison" subtitle="Two periods side by side — year to year, half, quarter, month or custom dates">
        <div class="no-print" style="display:flex; gap:8px; align-items:center;">
            <x-guide-button name="compare" />
            <button type="button" class="btn btn-secondary" x-data x-on:click="window.print()"><x-icon name="print" /> Print</button>
            <a href="{{ $exportUrl }}" class="btn btn-secondary" style="text-decoration:none;"><x-icon name="download" /> Export CSV</a>
        </div>
    </x-page-header>

    <div class="content">
        <a href="{{ route('dashboard') }}" wire:navigate class="back-link no-print" style="text-decoration:none;"><x-icon name="arrow-left" /> Dashboard</a>

        {{-- Printed instead of the controls: exactly what is being compared, and when. --}}
        <div class="print-only compare-print-head">
            <strong>{{ $comparison['left']['label'] }} vs {{ $comparison['right']['label'] }}</strong>
            <div>{{ $comparison['left']['range'] }} · {{ $comparison['left']['filters'] }}</div>
            <div>{{ $comparison['right']['range'] }} · {{ $comparison['right']['filters'] }}</div>
            <div class="compare-muted">Printed {{ now()->format('M j, Y g:i A') }} by {{ auth()->user()->name }}</div>
        </div>

        {{-- Controls: one side each --}}
        <div class="compare-controls no-print">
            @foreach (['left' => 'Left', 'right' => 'Right'] as $side => $sideLabel)
                @php($locked = $side === 'right' && $sameFilters)
                <div class="card compare-side" wire:key="controls-{{ $side }}">
                    <div class="compare-side-title">{{ $sideLabel }} — <span>{{ $comparison[$side]['label'] }}</span></div>
                    <div class="compare-side-fields">
                        <div class="field">
                            <x-field-label :for="$side.'Year'">Year</x-field-label>
                            <select id="{{ $side }}Year" wire:model.live="{{ $side }}Year" @disabled($this->{$side.'Period'} === 'custom')>
                                @foreach ($years as $year)
                                    <option value="{{ $year }}">{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <x-field-label :for="$side.'Period'" tip="A full year, a half, a quarter, one month, or your own dates.">Period</x-field-label>
                            <select id="{{ $side }}Period" wire:model.live="{{ $side }}Period">
                                @foreach ($periods as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($this->{$side.'Period'} === 'custom')
                            <div class="field">
                                <x-field-label :for="$side.'From'">From</x-field-label>
                                <input type="date" id="{{ $side }}From" wire:model.live="{{ $side }}From">
                            </div>
                            <div class="field">
                                <x-field-label :for="$side.'To'">To</x-field-label>
                                <input type="date" id="{{ $side }}To" wire:model.live="{{ $side }}To">
                            </div>
                        @endif
                        <div class="field">
                            <x-field-label :for="$side.'Line'" :tip="$locked ? 'Following the left side — untick “Same filters on both sides” to choose separately.' : null">Business line</x-field-label>
                            <select id="{{ $side }}Line" wire:model.live="{{ $side }}Line" @disabled($locked)>
                                <option value="">All business lines</option>
                                @foreach ($lines as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <x-field-label :for="$side.'Farm'">Farm</x-field-label>
                            <select id="{{ $side }}Farm" wire:model.live="{{ $side }}Farm" @disabled($locked)>
                                <option value="">All farms</option>
                                @foreach ($farms as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="compare-range">{{ $comparison[$side]['range'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="compare-switches no-print">
            <span class="compare-switch-row">
                <label class="compare-switch"><input type="checkbox" wire:model.live="linkYears"> Consecutive years</label>
                <x-tip text="The left year is always the year before the right one: pick 2025 on the left and the right becomes 2026; pick 2024 on the right and the left becomes 2023. Untick to choose any two years." />
            </span>
            <label class="compare-switch"><input type="checkbox" wire:model.live="sameFilters"> Same filters on both sides</label>
            <span class="compare-switch-row">
                <label class="compare-switch"><input type="checkbox" wire:model.live="sameSpan"> Same span when a period isn't over yet</label>
                <x-tip text="An unfinished period (like this year) is cut at today, and the other side is cut to the same number of days — so a part-year is never compared with a whole one." />
            </span>
            <button type="button" class="btn btn-ghost compare-clear" wire:click="clear"><x-icon name="reset" /> Clear</button>
        </div>
        @if ($comparison['cut'])
            <div class="compare-note no-print">One period isn't over yet, so both sides were cut to the same span: {{ $comparison['left']['range'] }} and {{ $comparison['right']['range'] }}.</div>
        @endif

        {{-- Highlights --}}
        <div class="card compare-highlights">
            <div class="section-title" style="margin:0 0 6px;">Highlights</div>
            <ul>
                @foreach ($comparison['highlights'] as $highlight)
                    <li wire:key="highlight-{{ $loop->index }}">{{ $highlight }}</li>
                @endforeach
            </ul>
        </div>

        {{-- Headline figures --}}
        <div class="card compare-card">
            <table class="compare-table">
                <thead>
                    <tr>
                        <th></th>
                        <th class="num">{{ $comparison['left']['label'] }}<div class="compare-muted">{{ $comparison['left']['filters'] }}</div></th>
                        <th class="num">{{ $comparison['right']['label'] }}<div class="compare-muted">{{ $comparison['right']['filters'] }}</div></th>
                        <th class="num">Change</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($comparison['rows'] as $row)
                        <tr wire:key="row-{{ $row['key'] }}">
                            <td>{{ $row['label'] }}</td>
                            <td class="num tnum">{{ $row['left_text'] }}</td>
                            <td class="num tnum">{{ $row['right_text'] }}</td>
                            <td class="num"><span class="compare-change tone-{{ $row['tone'] }}">{{ $row['change'] ?? '—' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Monthly issuance, both periods on one chart --}}
        <div class="card compare-card">
            <div class="compare-card-head">
                <div class="section-title" style="margin:0;">CARs issued per month</div>
                <div class="compare-legend">
                    <span><i class="legend-dot is-left"></i>{{ $comparison['left']['label'] }}</span>
                    <span><i class="legend-dot is-right"></i>{{ $comparison['right']['label'] }}</span>
                </div>
            </div>
            <div class="compare-chart">
                @foreach ($comparison['months'] as $pair)
                    <div class="compare-chart-col" wire:key="month-{{ $loop->index }}">
                        <div class="compare-chart-bars">
                            <div class="compare-bar is-left" style="height:{{ max(2, round(($pair['left']['count'] ?? 0) / $maxMonth * 100)) }}%" title="{{ $pair['left']['label'] ?? '' }}: {{ $pair['left']['count'] ?? 0 }}"><span>{{ $pair['left']['count'] ?? '' }}</span></div>
                            <div class="compare-bar is-right" style="height:{{ max(2, round(($pair['right']['count'] ?? 0) / $maxMonth * 100)) }}%" title="{{ $pair['right']['label'] ?? '' }}: {{ $pair['right']['count'] ?? 0 }}"><span>{{ $pair['right']['count'] ?? '' }}</span></div>
                        </div>
                        <div class="compare-chart-label">{{ str($pair['right']['label'] ?? $pair['left']['label'] ?? '')->before(' ') }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Breakdowns --}}
        <div class="compare-breakdowns">
            @foreach ($comparison['breakdowns'] as $key => $breakdown)
                <div class="card compare-card" wire:key="breakdown-{{ $key }}">
                    <div class="section-title" style="margin:0 0 8px;">{{ $breakdown['title'] }}</div>
                    <table class="compare-table compact">
                        <thead>
                            <tr>
                                <th></th>
                                <th class="num">{{ $comparison['left']['label'] }}</th>
                                <th class="num">{{ $comparison['right']['label'] }}</th>
                                <th class="num">Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($breakdown['rows'] as $row)
                                <tr wire:key="breakdown-{{ $key }}-{{ $loop->index }}">
                                    <td>{{ $row['name'] }}@if ($row['detail'])<span class="compare-muted"> · {{ $row['detail'] }}</span>@endif</td>
                                    <td class="num tnum">{{ $row['left'] }}</td>
                                    <td class="num tnum">{{ $row['right'] }}</td>
                                    <td class="num"><span class="compare-change tone-{{ $row['tone'] }}">{{ $row['change'] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="empty-row">No CARs in either period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </div>

    <x-guides.compare />
</section>
