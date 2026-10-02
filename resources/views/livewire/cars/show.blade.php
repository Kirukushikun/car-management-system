@use('App\Services\ScaffoldData')

<section>
    <x-page-header :title="$car['ref'].' — '.$car['subcategory']"
                   :subtitle="$car['farm'].' · '.$car['unit'].' · '.$car['line'].' · issued '.\Carbon\CarbonImmutable::parse($car['issued'])->format('M j, Y')">
        <div>
            <x-pill :tone="ScaffoldData::statusTone($car['status'])">{{ $car['status'] }}</x-pill>
            @if ($isOverdue)
                <x-pill tone="red" style="margin-left:6px;">overdue</x-pill>
            @endif
        </div>
    </x-page-header>

    <div class="content">
        <a href="{{ url()->previous() === url()->current() ? route('cars.index') : url()->previous() }}" wire:navigate class="back-link" style="text-decoration:none;">&larr; Back</a>

        @if ($notice)
            <div class="flash" wire:key="notice">{{ $notice }}</div>
        @endif

        @if ($actions)
            <div class="card action-bar" style="background:var(--accent-bg); border-color:var(--accent-bd);">
                <div class="who">Your action — {{ auth()->user()->roleWithScope() }}</div>
                <div class="note">{{ $actions['note'] }}</div>
                @if ($actions['asks_new_end_date'])
                    <div class="field" style="max-width:240px; margin-bottom:10px;">
                        <label for="newEndDate">New end date (only used if not accepted)</label>
                        <input type="date" id="newEndDate" wire:model="newEndDate">
                    </div>
                @endif
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    @foreach ($actions['buttons'] as $button)
                        <button type="button" wire:key="action-{{ $loop->index }}"
                                class="btn {{ $button['primary'] ? 'btn-accent' : 'btn-secondary' }}"
                                wire:click="runAction(@js($button['label']))">{{ $button['label'] }}</button>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="detail-grid">
            {{-- Phase I --}}
            <div class="card">
                <div class="phase-head">
                    <div class="phase-num" style="background:var(--blue-bg); color:var(--blue)">I</div>
                    <strong>Initiation &amp; Submission</strong>
                </div>
                <div class="phase-body">
                    <div class="field-grid">
                        <div class="kv"><span class="k">Issued to</span><span class="v">{{ $car['unit'] }} ({{ $car['line'] }})</span></div>
                        <div class="kv"><span class="k">Complainant</span><span class="v">{{ $car['complainant'] }}</span></div>
                        <div class="kv"><span class="k">Category</span><span class="v">{{ $car['category'] }}</span></div>
                        <div class="kv"><span class="k">Sub-category</span><span class="v">{{ $car['subcategory'] }}</span></div>
                    </div>
                    <div class="deadline-preview surface" style="margin-top:2px;">
                        <div class="box">
                            <div class="lbl">Response deadline</div>
                            <div class="date tnum" style="font-size:13px;">{{ $deadlines['response']->format('M j, Y') }} <span style="font-weight:400;color:var(--text3)">(+{{ $deadlines['response_days'] }}d)</span></div>
                        </div>
                        <div class="box" style="border-left:.5px solid var(--border);">
                            <div class="lbl">Implementation deadline</div>
                            <div class="date tnum" style="font-size:13px;">{{ $deadlines['implementation']->format('M j, Y') }} <span style="font-weight:400;color:var(--text3)">(+{{ $deadlines['implementation_days'] }}d)</span></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Phase II --}}
            <div class="card" style="opacity:{{ $phase >= 2 ? 1 : .5 }}">
                <div class="phase-head">
                    <div class="phase-num" style="background:var(--violet-bg); color:var(--violet)">II</div>
                    <strong>Response, Root Cause &amp; Action Planning</strong>
                    @if ($phase === 2 && $owner['role'])
                        <span class="phase-owner"><x-pill :tone="$owner['role']->tone()">{{ $owner['label'] }}</x-pill></span>
                    @endif
                </div>
                <div class="phase-body">
                    @if ($phase >= 2)
                        <div class="kv"><span class="k">Interim containment</span><span class="v">Segregated affected batch; responder notified within 4 hrs</span></div>
                        <div class="kv"><span class="k">Root cause</span><span class="v">Grading calibration drift on Line 2 — last verified 11 days prior</span></div>
                        <div class="kv"><span class="k">Corrective action</span><span class="v">Recalibrate grading line, retrain shift operators, add daily calibration check</span></div>
                        <div class="kv"><span class="k">Approval</span><span class="v">
                            @switch($car['status'])
                                @case('Awaiting Responder Approval') Awaiting {{ $car['farm'] }} Responder Approver sign-off @break
                                @case('Returned to Responder') Returned for revision — Responder needs to resubmit @break
                                @default Approved — routed to Phase III
                            @endswitch
                        </span></div>
                    @else
                        <div style="color:var(--text3); font-size:11.5px;">Not started — the Responder has not submitted a response yet.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Phase III --}}
        <div class="card" style="margin-top:14px; opacity:{{ $phase >= 3 ? 1 : .5 }}">
            <div class="phase-head">
                <div class="phase-num" style="background:var(--green-bg); color:var(--green)">III</div>
                <strong>Implementation, Verification &amp; Closure</strong>
                @if ($phase === 3 && $owner['role'])
                    <span class="phase-owner"><x-pill :tone="$owner['role']->tone()">{{ $owner['label'] }}</x-pill></span>
                @endif
            </div>
            <div class="phase-body">
                @if ($phase >= 3)
                    <div class="kv"><span class="k">Evidence</span><span class="v">{{ $car['status'] === 'Awaiting Implementation' ? 'Not yet uploaded' : 'Calibration log + photo evidence uploaded' }}</span></div>
                    <div class="kv"><span class="k">Effectiveness check</span><span class="v">{{ in_array($car['status'], ['Closed — Accepted', 'Open — Not Accepted', 'Awaiting Requestor Approval'], true) ? 'Effective — forwarded to the Requestor Approver' : 'Pending' }}</span></div>
                    <div class="kv"><span class="k">Final acceptance</span><span class="v">
                        @switch($car['status'])
                            @case('Closed — Accepted') Accepted — CAR closed @break
                            @case('Open — Not Accepted') Not accepted — revised end date {{ \Carbon\CarbonImmutable::parse($car['new_end'])->format('M j, Y') }}, looped back to Step 11 @break
                            @default Pending
                        @endswitch
                    </span></div>
                @else
                    <div style="color:var(--text3); font-size:11.5px;">Not started.</div>
                @endif
            </div>
        </div>

        <div class="card" style="margin-top:14px; padding:16px 18px;">
            <div class="section-title">History</div>
            <div class="timeline">
                @foreach ($car['history'] as $entry)
                    <div @class(['tl-entry', 'tone-'.$entry['tone'] => $entry['tone'] !== 'default']) wire:key="history-{{ $loop->index }}">
                        <div class="tl-head"><span>{{ $entry['who'] }}</span><span class="tl-date tnum">{{ \Carbon\CarbonImmutable::parse($entry['date'])->format('M j, Y') }}</span></div>
                        <div class="tl-body">{{ $entry['what'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
