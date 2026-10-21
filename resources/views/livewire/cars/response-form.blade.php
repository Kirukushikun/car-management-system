<form x-on:submit.prevent id="response-form" class="embedded-form">
    <div class="embedded-form-head">
        <strong>{{ $heading }} — solution {{ $car->current_round }}</strong>
        <span>Steps 7–9 · submit for Concerned Department Approver sign-off</span>
    </div>
    @if ($notice)
        <div class="flash" style="margin-bottom:0;">{{ $notice }}</div>
    @endif
    @error('response') <div class="flash" style="background:var(--red-bg); color:var(--red); border-color:var(--red-bd); margin-bottom:0;">{{ $message }}</div> @enderror

    {{-- Step 7 --}}
    <div class="section-title" style="margin:4px 0 0;">Step 7 · Interim containment / immediate actions</div>
    <div class="field">
        <x-field-label for="containmentActions" tip="Quick actions taken right away so the problem goes no further — before the root cause is known. For example: held the batch, replaced the customer's stock.">What was done right away to contain the issue</x-field-label>
        <x-format-toolbar />
        <textarea id="containmentActions" maxlength="{{ \App\Support\TextLimits::LONG }}" wire:model="containmentActions" placeholder="e.g. Held the remaining stock from the same batch, replaced the customer's spoiled eggs..."></textarea>
        <x-char-count />
        @error('containmentActions') <div class="error-text">{{ $message }}</div> @enderror
    </div>
    <div class="field-grid" style="grid-template-columns:1fr 1fr 1.4fr;">
        <div class="field">
            <x-field-label for="containmentStartsOn" tip="When the containment actions began.">Date start</x-field-label>
            <input id="containmentStartsOn" type="date" wire:model="containmentStartsOn">
            @error('containmentStartsOn') <div class="error-text">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <x-field-label for="containmentEndsOn" tip="When containment ended or will end. Corrective actions start from this date.">Date end</x-field-label>
            <input id="containmentEndsOn" type="date" wire:model.live="containmentEndsOn">
            @error('containmentEndsOn') <div class="error-text">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <x-field-label for="containmentResponsible" tip="Who carried out the containment.">Responsible person</x-field-label>
            <input id="containmentResponsible" maxlength="{{ \App\Support\TextLimits::SHORT }}" wire:model="containmentResponsible">
            <x-char-count />
            @error('containmentResponsible') <div class="error-text">{{ $message }}</div> @enderror
        </div>
    </div>

    {{-- Step 8 --}}
    <div class="section-title" style="margin:10px 0 0;">Step 8 · Root cause</div>
    <div class="field">
        <x-field-label for="rootCause" tip="Why it happened — the underlying cause, not the symptom. Type it, attach your analysis (e.g. a 5-Why or fishbone), or both.">Root cause findings — type them, attach a file, or both</x-field-label>
        <x-format-toolbar />
        <textarea id="rootCause" maxlength="{{ \App\Support\TextLimits::LONG }}" wire:model="rootCause" placeholder="Identify and define the root cause..."></textarea>
        <x-char-count />
        @error('rootCause') <div class="error-text">{{ $message }}</div> @enderror
    </div>
    <div class="field-grid">
        <div class="field">
            <x-field-label for="rootCauseResponsible" tip="Who investigated and identified the root cause.">Responsible person</x-field-label>
            <input id="rootCauseResponsible" maxlength="{{ \App\Support\TextLimits::SHORT }}" wire:model="rootCauseResponsible">
            <x-char-count />
            @error('rootCauseResponsible') <div class="error-text">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <x-field-label for="rootCauseFiles" tip="Optional analysis documents or photos — PDF, Office files or images, up to 50 MB each.">Root cause files (optional)</x-field-label>
            <input id="rootCauseFiles" type="file" wire:model="rootCauseFiles" multiple accept=".{{ implode(',.', \App\Models\Attachment::ALLOWED_EXTENSIONS) }}">
            <div wire:loading wire:target="rootCauseFiles" class="hint">Uploading…</div>
            @error('rootCauseFiles.*') <div class="error-text">{{ $message }}</div> @enderror
        </div>
    </div>

    {{-- Step 9 --}}
    <div class="section-title" style="margin:10px 0 0;">Step 9 · Corrective actions</div>
    <div class="hint" style="font-size:10.5px; color:var(--text3); margin-top:-6px;">
        Each action starts when containment ends and must finish by the implementation deadline — {{ $deadline->format('M j, Y') }}.
    </div>
    @error('correctiveActions') <div class="error-text">{{ $message }}</div> @enderror

    @foreach ($correctiveActions as $index => $action)
        <div class="surface" style="padding:12px 14px;" wire:key="action-{{ $index }}">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong style="font-size:11.5px;">Action {{ $index + 1 }}</strong>
                @if (count($correctiveActions) > 1)
                    <button type="button" class="btn btn-ghost" style="padding:0 6px;" wire:click="removeAction({{ $index }})">Remove</button>
                @endif
            </div>
            <div class="field">
                <x-field-label :for="'action-'.$index.'-description'" tip="A lasting fix aimed at the root cause so the problem does not come back. One action per row — use “Add corrective action” for more.">Corrective action</x-field-label>
                <x-format-toolbar />
                <textarea id="action-{{ $index }}-description" maxlength="{{ \App\Support\TextLimits::LONG }}" wire:model="correctiveActions.{{ $index }}.description" style="min-height:60px;"></textarea>
                <x-char-count />
                @error("correctiveActions.{$index}.description") <div class="error-text">{{ $message }}</div> @enderror
            </div>
            <div class="field-grid" style="grid-template-columns:1.4fr 1fr 1fr; margin-top:10px;">
                <div class="field">
                    <x-field-label :for="'action-'.$index.'-responsible'" tip="Who will carry out this action.">Responsible</x-field-label>
                    <input id="action-{{ $index }}-responsible" maxlength="{{ \App\Support\TextLimits::SHORT }}" wire:model="correctiveActions.{{ $index }}.responsible">
                    <x-char-count />
                    @error("correctiveActions.{$index}.responsible") <div class="error-text">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <x-field-label :for="'action-'.$index.'-starts'" tip="When this action starts — on or after the containment end date.">Date start</x-field-label>
                    <input id="action-{{ $index }}-starts" type="date" wire:model="correctiveActions.{{ $index }}.starts_on">
                    @error("correctiveActions.{$index}.starts_on") <div class="error-text">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <x-field-label :for="'action-'.$index.'-ends'" :tip="'When this action will be done — no later than the implementation deadline, '.$deadline->format('M j, Y').'.'">Date end</x-field-label>
                    <input id="action-{{ $index }}-ends" type="date" wire:model="correctiveActions.{{ $index }}.ends_on" max="{{ $deadline->toDateString() }}">
                    @error("correctiveActions.{$index}.ends_on") <div class="error-text">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    @endforeach

    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <button type="button" class="btn btn-secondary" wire:click="addAction"><x-icon name="plus" /> Add corrective action</button>
        <div class="field" style="min-width:260px;">
            <x-field-label for="correctiveActionFiles" tip="Optional supporting documents — procedures, checklists, training records. PDF, Office files or images, up to 50 MB each.">Corrective action documents (optional)</x-field-label>
            <input id="correctiveActionFiles" type="file" wire:model="correctiveActionFiles" multiple accept=".{{ implode(',.', \App\Models\Attachment::ALLOWED_EXTENSIONS) }}">
            <div wire:loading wire:target="correctiveActionFiles" class="hint">Uploading…</div>
            @error('correctiveActionFiles.*') <div class="error-text">{{ $message }}</div> @enderror
        </div>
    </div>

    @if ($existingFiles->isNotEmpty())
        <div class="kv">
            <span class="k">Files already attached</span>
            @foreach ($existingFiles as $file)
                <a href="{{ route('attachments.show', $file) }}" target="_blank" style="font-size:11.5px;" wire:key="file-{{ $file->id }}">
                    {{ $file->original_name }} <span style="color:var(--text3);">· {{ $file->collection === \App\Models\Attachment::ROOT_CAUSE ? 'root cause' : 'corrective action' }} · {{ $file->humanSize() }}</span>
                </a>
            @endforeach
        </div>
    @endif

    @error('submission') <div class="flash" style="background:var(--red-bg); color:var(--red); border-color:var(--red-bd); margin:0;" role="alert">{{ $message }}</div> @enderror

    <div style="display:flex; justify-content:flex-end; gap:10px; padding-top:12px; border-top:.5px solid var(--border);">
        <button type="button" class="btn btn-secondary" wire:click="saveDraft" wire:loading.attr="disabled">Save draft</button>
        <button type="button" class="btn btn-accent" wire:loading.attr="disabled" wire:target="submit,rootCauseFiles,correctiveActionFiles"
                x-on:click="$dispatch('confirm', { ...@js(['title' => 'Submit the response for approval?', 'message' => \App\Enums\CarAction::SubmitResponse->confirmation(), 'confirmLabel' => 'Submit for approval']), run: () => $wire.submit() })">Submit for approval</button>
    </div>
</form>
