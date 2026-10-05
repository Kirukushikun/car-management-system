@use('App\Enums\CarAction')

<section>
    <x-page-header :title="$car->reference.' — '.$car->subcategory->name"
                   :subtitle="$car->farm->name.' · '.$car->issuedToUnit->name.' · '.$car->issuedToUnit->businessLine->name.' · issued '.$car->issued_on->format('M j, Y')">
        <div>
            <x-pill :tone="$car->status->tone()">{{ $car->status->label() }}</x-pill>
            @if ($isOverdue)
                <x-pill tone="red" style="margin-left:6px;">overdue</x-pill>
            @endif
        </div>
    </x-page-header>

    <div class="content">
        <a href="{{ route('cars.index') }}" wire:navigate class="back-link" style="text-decoration:none;">&larr; All CARs</a>

        @if (session('status'))
            <div class="flash" style="background:var(--green-bg); color:var(--green); border-color:var(--green-bd);">{{ session('status') }}</div>
        @endif
        @if ($notice)
            <div class="flash" wire:key="notice">{{ $notice }}</div>
        @endif

        @if ($actions)
            <div class="card action-bar" style="background:var(--accent-bg); border-color:var(--accent-bd);" wire:key="action-bar">
                <div class="who">Your action — {{ auth()->user()->roleWithScope() }}</div>
                <div class="note">{{ $actionNote }}</div>

                @if ($needsNote)
                    <div class="field" style="margin-bottom:10px;">
                        <label for="note">Reason (required to reject, return or void)</label>
                        <textarea id="note" wire:model="note" style="min-height:60px;"></textarea>
                        @error('note') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                @endif

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    @foreach ($actions as $action)
                        @if ($action === CarAction::Resubmit)
                            <a href="{{ route('cars.edit', $car) }}" wire:navigate class="btn btn-accent" style="text-decoration:none;">Correct &amp; resubmit</a>
                        @else
                            <button type="button" wire:key="action-{{ $action->value }}"
                                    @class(['btn', 'btn-accent' => $loop->first && ! $action->requiresNote(), 'btn-secondary' => ! ($loop->first && ! $action->requiresNote())])
                                    wire:click="act('{{ $action->value }}')"
                                    wire:loading.attr="disabled">{{ $action->label() }}</button>
                        @endif
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
                    @if ($car->status->phase() === 1 && $car->ownerLabel())
                        <span class="phase-owner"><x-pill :tone="$car->status->ownerRole()->tone()">{{ $car->ownerLabel() }}</x-pill></span>
                    @endif
                </div>
                <div class="phase-body">
                    <div class="field-grid">
                        <div class="kv"><span class="k">Issued to</span><span class="v">{{ $car->issuedToUnit->name }} ({{ $car->issuedToUnit->businessLine->name }})</span></div>
                        <div class="kv"><span class="k">Responding farm</span><span class="v">{{ $car->farm->name }}</span></div>
                        <div class="kv"><span class="k">Complainant</span><span class="v">{{ $car->complainant }}</span></div>
                        <div class="kv"><span class="k">Type of complaint</span><span class="v">{{ $car->complaint_type->label() }}</span></div>
                        <div class="kv"><span class="k">Category</span><span class="v">{{ $car->category->name }}</span></div>
                        <div class="kv"><span class="k">Sub-category</span><span class="v">{{ $car->subcategory->name }}</span></div>
                        <div class="kv"><span class="k">Issued by</span><span class="v">{{ $car->issued_by }}</span></div>
                        <div class="kv"><span class="k">Complaint received</span><span class="v">{{ $car->complaint_received_on?->format('M j, Y') ?? '—' }}</span></div>
                    </div>
                    <div class="kv"><span class="k">Details of the problem</span><span class="v" style="white-space:pre-line; font-weight:400;">{{ $car->problem_details }}</span></div>

                    @if ($car->attachments->isNotEmpty())
                        <div class="kv">
                            <span class="k">Attachments</span>
                            <div style="display:flex; flex-direction:column; gap:4px; margin-top:2px;">
                                @foreach ($car->attachments as $attachment)
                                    <a href="{{ route('attachments.show', $attachment) }}" target="_blank" style="font-size:12px;" wire:key="attachment-{{ $attachment->id }}">
                                        {{ $attachment->isVideo() ? '🎬' : ($attachment->isImage() ? '🖼' : '📄') }} {{ $attachment->original_name }}
                                        <span style="color:var(--text3);">· {{ $attachment->humanSize() }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="deadline-preview surface" style="margin-top:2px;">
                        <div class="box">
                            <div class="lbl">Response deadline</div>
                            <div class="date tnum" style="font-size:13px;">{{ $car->response_due_on->format('M j, Y') }} <span style="font-weight:400;color:var(--text3)">(+{{ $car->response_days }}d)</span></div>
                        </div>
                        <div class="box" style="border-left:.5px solid var(--border);">
                            <div class="lbl">{{ $car->revised_due_on ? 'Revised end date' : 'Implementation deadline' }}</div>
                            <div class="date tnum" style="font-size:13px;">
                                {{ ($car->revised_due_on ?? $car->implementation_due_on)->format('M j, Y') }}
                                @unless ($car->revised_due_on)
                                    <span style="font-weight:400;color:var(--text3)">(+{{ $car->implementation_days }}d)</span>
                                @endunless
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Phase II --}}
            <div class="card" style="opacity:{{ $car->released_at ? 1 : .5 }}">
                <div class="phase-head">
                    <div class="phase-num" style="background:var(--violet-bg); color:var(--violet)">II</div>
                    <strong>Response, Root Cause &amp; Action Planning</strong>
                    @if ($car->status->phase() === 2 && $car->ownerLabel())
                        <span class="phase-owner"><x-pill :tone="$car->status->ownerRole()->tone()">{{ $car->ownerLabel() }}</x-pill></span>
                    @endif
                </div>
                <div class="phase-body">
                    @if (! $car->released_at)
                        <div style="color:var(--text3); font-size:11.5px;">Not started — the CAR has not been released to the Responder yet.</div>
                    @else
                        <div style="color:var(--text3); font-size:11.5px;">Interim containment, root cause and corrective actions are entered here from Phase 4 of the build. Round {{ $car->current_round }}.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Phase III --}}
        <div class="card" style="margin-top:14px; opacity:{{ $car->status->phase() === 3 || ! $car->status->isOpen() ? 1 : .5 }}">
            <div class="phase-head">
                <div class="phase-num" style="background:var(--green-bg); color:var(--green)">III</div>
                <strong>Implementation, Verification &amp; Closure</strong>
                @if ($car->status->phase() === 3 && $car->ownerLabel())
                    <span class="phase-owner"><x-pill :tone="$car->status->ownerRole()->tone()">{{ $car->ownerLabel() }}</x-pill></span>
                @endif
            </div>
            <div class="phase-body">
                <div style="color:var(--text3); font-size:11.5px;">
                    @if ($car->closed_at)
                        Closed — accepted on {{ $car->closed_at->format('M j, Y') }}.
                    @elseif ($car->voided_at)
                        Voided on {{ $car->voided_at->format('M j, Y') }}.
                    @else
                        Evidence upload, effectiveness check and final acceptance are entered here from Phase 5 of the build.
                    @endif
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:14px; padding:16px 18px;">
            <div class="section-title">History</div>
            <div class="timeline">
                @foreach ($car->events as $event)
                    @php
                        $tone = match ($event->action) {
                            CarAction::Release, CarAction::ApproveResponse, CarAction::MarkEffective, CarAction::Accept => 'green',
                            CarAction::Reject, CarAction::ReturnResponse, CarAction::MarkNotEffective, CarAction::NotAccept, CarAction::Void => 'red',
                            default => null,
                        };
                    @endphp
                    <div @class(['tl-entry', "tone-{$tone}" => $tone]) wire:key="event-{{ $event->id }}">
                        <div class="tl-head">
                            <span>{{ $event->actor?->name ?? 'System' }} <span style="font-weight:400; color:var(--text3);">— {{ $event->actor?->role->label() }}</span></span>
                            <span class="tl-date tnum">{{ $event->created_at->format('M j, Y g:i A') }}</span>
                        </div>
                        <div class="tl-body">
                            {{ $event->action->pastTense() }}.
                            @if ($event->action === CarAction::NotAccept && $car->revised_due_on)
                                New end date: {{ $car->revised_due_on->format('M j, Y') }}.
                            @endif
                        </div>
                        @if ($event->note)
                            <div class="tl-body" style="color:var(--text);">“{{ $event->note }}”</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
