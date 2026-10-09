@use('App\Enums\CarAction')

<section>
    <x-page-header :title="$car->reference.' — '.$car->subcategory->name"
                   :subtitle="$car->farm->name.' · '.$car->issuedToUnit->name.' · '.$car->issuedToUnit->businessLine->name.' · issued '.$car->issued_on->format('M j, Y')">
        <div style="display:flex; gap:8px; align-items:center;">
            <a href="{{ route('cars.print', $car) }}" target="_blank" class="btn btn-secondary" style="text-decoration:none;">Print form</a>
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
                @if ($sentBack)
                    <div class="sent-back">
                        <strong>{{ $sentBack->action->label() }}</strong> by {{ $sentBack->actor?->name }} · {{ $sentBack->created_at->format('M j, Y') }}
                        <div>“{{ $sentBack->note }}”</div>
                    </div>
                @endif

                @if ($needsNewDueDate)
                    <div class="field" style="max-width:260px; margin-bottom:10px;">
                        <label for="newDueOn">New end date (required if not accepted)</label>
                        <input type="date" id="newDueOn" wire:model="newDueOn" min="{{ now()->addDay()->toDateString() }}">
                        @error('new_due_on') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                @endif

                @if ($needsNote)
                    <div class="field" style="margin-bottom:10px;">
                        <label for="note">{{ $noteLabel }}</label>
                        <textarea id="note" wire:model="note" style="min-height:60px;"></textarea>
                        @error('note') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                @endif

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    @foreach ($actions as $action)
                        @if ($action === CarAction::Resubmit)
                            <a href="{{ route('cars.edit', $car) }}" wire:navigate class="btn btn-accent" style="text-decoration:none;">Correct &amp; resubmit</a>
                        @elseif ($action === CarAction::SubmitResponse)
                            <a href="#response-form" class="btn btn-accent" style="text-decoration:none;">Fill in the response below</a>
                        @elseif ($action === CarAction::UploadEvidence)
                            <a href="#evidence-form" class="btn btn-accent" style="text-decoration:none;">Upload the evidence below</a>
                        @else
                            <button type="button" wire:key="action-{{ $action->value }}"
                                    @class(['btn', 'btn-accent' => $loop->first && ! $action->requiresNote(), 'btn-secondary' => ! ($loop->first && ! $action->requiresNote())])
                                    x-on:click="$dispatch('confirm', { ...@js(['title' => $action->label().' — '.$car->reference.'?', 'message' => $action->confirmation(), 'confirmLabel' => $action->label(), 'danger' => $action->isNegative()]), run: () => $wire.act(@js($action->value)) })"
                                    wire:loading.attr="disabled">{{ $action->label() }}</button>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- While the Responder fills in Phase II, it takes the full width so the form fits; Phase I sits above it. --}}
        <div class="detail-grid" @style(['grid-template-columns:1fr' => $canRespond])>
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
                    @if ($canRespond)
                        <livewire:cars.response-form :car="$car" :key="'response-form-'.$car->id.'-'.$car->current_round" />
                    @endif

                    @if (! $car->released_at)
                        <div style="color:var(--text3); font-size:11.5px;">Not started — the CAR has not been released to the Responder yet.</div>
                    @elseif ($submittedResponses->isEmpty())
                        @unless ($canRespond)
                            <div style="color:var(--text3); font-size:11.5px;">Waiting for {{ $car->farm->name }}'s response — interim containment, root cause and corrective actions.</div>
                        @endunless
                    @else
                        @if ($canRespond)
                            <div class="section-title" style="margin:6px 0 0; padding-top:14px; border-top:.5px solid var(--border);">Earlier solutions</div>
                        @endif
                        @foreach ($submittedResponses as $response)
                            <div wire:key="response-{{ $response->id }}" @style(['padding-top:12px; border-top:.5px solid var(--border)' => ! $loop->first])>
                                <x-car-response :response="$response" :show-round="$car->current_round > 1" :outcome="$solutionOutcomes[$response->round->number] ?? null" />
                            </div>
                        @endforeach
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
                @if ($car->closed_at)
                    <div class="flash" style="background:var(--green-bg); color:var(--green); border-color:var(--green-bd); margin:0;">Closed — accepted on {{ $car->closed_at->format('M j, Y') }}.</div>
                @elseif ($car->voided_at)
                    <div class="flash" style="margin:0;">Voided on {{ $car->voided_at->format('M j, Y') }}.</div>
                @endif

                @if ($canUploadEvidence)
                    <livewire:cars.evidence-form :car="$car" :key="'evidence-form-'.$car->id.'-'.$car->current_round" />
                    @if ($verificationRounds)
                        <div class="section-title" style="margin:6px 0 0; padding-top:14px; border-top:.5px solid var(--border);">Earlier solutions</div>
                    @endif
                @endif

                @forelse ($verificationRounds as $row)
                    <div wire:key="verification-{{ $row['round']->id }}" @style(['padding-top:12px; border-top:.5px solid var(--border)' => ! $loop->first])>
                        @if (count($verificationRounds) > 1 || $row['round']->number > 1)
                            <x-solution-badge :number="$row['round']->number" :outcome="$solutionOutcomes[$row['round']->number] ?? null" style="margin-bottom:8px;" />
                        @endif
                        <div class="field-grid">
                            <div class="kv">
                                <span class="k">Step 11 · Implementation evidence</span>
                                @if ($row['round']->evidence_uploaded_at)
                                    <span class="v" style="font-weight:400;">
                                        {{ $row['round']->evidence_responsible }} · uploaded {{ $row['round']->evidence_uploaded_at->format('M j, Y') }}
                                    </span>
                                    @if ($row['round']->evidence_notes)
                                        <span class="v" style="font-weight:400; white-space:pre-line; color:var(--text2);">{{ $row['round']->evidence_notes }}</span>
                                    @endif
                                    @foreach ($row['evidence'] as $file)
                                        <a href="{{ route('attachments.show', $file) }}" target="_blank" style="font-size:11.5px;">{{ $file->isVideo() ? '🎬' : ($file->isImage() ? '🖼' : '📄') }} {{ $file->original_name }} <span style="color:var(--text3);">· {{ $file->humanSize() }}</span></a>
                                    @endforeach
                                @else
                                    <span class="v" style="font-weight:400; color:var(--text3);">Not uploaded yet.</span>
                                @endif
                            </div>
                            <div style="display:flex; flex-direction:column; gap:10px;">
                                <div class="kv">
                                    <span class="k">Step 12 · Effectiveness check</span>
                                    @if ($row['verification'])
                                        <span class="v">
                                            <x-pill :tone="$row['verification']->action === CarAction::MarkEffective ? 'green' : 'red'">{{ $row['verification']->action === CarAction::MarkEffective ? 'Effective' : 'Not effective' }}</x-pill>
                                            <span style="font-weight:400; font-size:11.5px;">{{ $row['verification']->actor?->name }} · {{ $row['verification']->created_at->format('M j, Y') }}</span>
                                        </span>
                                        @if ($row['verification']->note)
                                            <span class="v" style="font-weight:400; color:var(--text2);">“{{ $row['verification']->note }}”</span>
                                        @endif
                                    @else
                                        <span class="v" style="font-weight:400; color:var(--text3);">Pending.</span>
                                    @endif
                                </div>
                                <div class="kv">
                                    <span class="k">Step 13 · Final acceptance</span>
                                    @if ($row['acceptance'])
                                        <span class="v">
                                            <x-pill :tone="$row['acceptance']->action === CarAction::Accept ? 'green' : 'red'">{{ $row['acceptance']->action === CarAction::Accept ? 'Accepted — closed' : 'Not accepted' }}</x-pill>
                                            <span style="font-weight:400; font-size:11.5px;">{{ $row['acceptance']->actor?->name }} · {{ $row['acceptance']->created_at->format('M j, Y') }}</span>
                                        </span>
                                        @if ($row['acceptance']->note)
                                            <span class="v" style="font-weight:400; color:var(--text2);">“{{ $row['acceptance']->note }}”</span>
                                        @endif
                                        @if ($row['acceptance']->action === CarAction::NotAccept)
                                            <span class="v" style="font-weight:400; color:var(--text2);">New end date: {{ $car->rounds->firstWhere('number', $row['round']->number + 1)?->due_on?->format('M j, Y') ?? '—' }}</span>
                                        @endif
                                    @else
                                        <span class="v" style="font-weight:400; color:var(--text3);">Pending.</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div style="color:var(--text3); font-size:11.5px;">
                        @if ($car->status->isOpen() && ! $canUploadEvidence)
                            Starts once the corrective actions are approved — the Responder uploads evidence, the Responder Approver checks effectiveness, and the Requestor Approver gives final acceptance.
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card" style="margin-top:14px; padding:16px 18px;" x-data="{ showAll: false }" wire:key="history">
            <div class="section-title">History</div>
            <div class="timeline">
                @foreach ($car->events as $event)
                    @if ($loop->index === $historyVisible)
                        <button type="button" class="btn btn-ghost history-toggle" x-show="! showAll" x-on:click="showAll = true">
                            Show {{ $loop->remaining + 1 }} more {{ str('entry')->plural($loop->remaining + 1) }}
                        </button>
                    @endif
                    @php
                        $tone = match ($event->action) {
                            CarAction::Release, CarAction::ApproveResponse, CarAction::MarkEffective, CarAction::Accept => 'green',
                            CarAction::Reject, CarAction::ReturnResponse, CarAction::MarkNotEffective, CarAction::NotAccept, CarAction::Void => 'red',
                            default => null,
                        };
                    @endphp
                    <div @class(['tl-entry', "tone-{$tone}" => $tone]) wire:key="event-{{ $event->id }}"
                         @if ($loop->index >= $historyVisible) x-show="showAll" x-cloak @endif>
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
                @if ($car->events->count() > $historyVisible)
                    <button type="button" class="btn btn-ghost history-toggle" x-show="showAll" x-cloak x-on:click="showAll = false">Show less</button>
                @endif
            </div>
        </div>
    </div>
</section>
