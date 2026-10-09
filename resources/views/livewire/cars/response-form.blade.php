<form x-on:submit.prevent id="response-form" class="embedded-form">
    <div class="embedded-form-head">
        <strong>{{ $heading }} — solution {{ $car->current_round }}</strong>
        <span>Steps 7–9 · submit for Responder Approver sign-off</span>
    </div>
    @if ($notice)
        <div class="flash" style="margin-bottom:0;">{{ $notice }}</div>
    @endif
    @error('response') <div class="flash" style="background:var(--red-bg); color:var(--red); border-color:var(--red-bd); margin-bottom:0;">{{ $message }}</div> @enderror

    {{-- Step 7 --}}
    <div class="section-title" style="margin:4px 0 0;">Step 7 · Interim containment / immediate actions</div>
    <div class="field">
        <label for="containmentActions">What was done right away to contain the issue</label>
        <textarea id="containmentActions" wire:model="containmentActions" placeholder="e.g. Held the remaining stock from the same batch, replaced the customer's spoiled eggs..."></textarea>
        @error('containmentActions') <div class="error-text">{{ $message }}</div> @enderror
    </div>
    <div class="field-grid" style="grid-template-columns:1fr 1fr 1.4fr;">
        <div class="field">
            <label for="containmentStartsOn">Date start</label>
            <input id="containmentStartsOn" type="date" wire:model="containmentStartsOn">
            @error('containmentStartsOn') <div class="error-text">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="containmentEndsOn">Date end</label>
            <input id="containmentEndsOn" type="date" wire:model.live="containmentEndsOn">
            @error('containmentEndsOn') <div class="error-text">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="containmentResponsible">Responsible person</label>
            <input id="containmentResponsible" wire:model="containmentResponsible">
            @error('containmentResponsible') <div class="error-text">{{ $message }}</div> @enderror
        </div>
    </div>

    {{-- Step 8 --}}
    <div class="section-title" style="margin:10px 0 0;">Step 8 · Root cause</div>
    <div class="field">
        <label for="rootCause">Root cause findings — type them, attach a file, or both</label>
        <textarea id="rootCause" wire:model="rootCause" placeholder="Identify and define the root cause..."></textarea>
        @error('rootCause') <div class="error-text">{{ $message }}</div> @enderror
    </div>
    <div class="field-grid">
        <div class="field">
            <label for="rootCauseResponsible">Responsible person</label>
            <input id="rootCauseResponsible" wire:model="rootCauseResponsible">
            @error('rootCauseResponsible') <div class="error-text">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="rootCauseFiles">Root cause files (optional)</label>
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
                <label for="action-{{ $index }}-description">Corrective action</label>
                <textarea id="action-{{ $index }}-description" wire:model="correctiveActions.{{ $index }}.description" style="min-height:60px;"></textarea>
                @error("correctiveActions.{$index}.description") <div class="error-text">{{ $message }}</div> @enderror
            </div>
            <div class="field-grid" style="grid-template-columns:1.4fr 1fr 1fr; margin-top:10px;">
                <div class="field">
                    <label for="action-{{ $index }}-responsible">Responsible</label>
                    <input id="action-{{ $index }}-responsible" wire:model="correctiveActions.{{ $index }}.responsible">
                    @error("correctiveActions.{$index}.responsible") <div class="error-text">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="action-{{ $index }}-starts">Date start</label>
                    <input id="action-{{ $index }}-starts" type="date" wire:model="correctiveActions.{{ $index }}.starts_on">
                    @error("correctiveActions.{$index}.starts_on") <div class="error-text">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="action-{{ $index }}-ends">Date end</label>
                    <input id="action-{{ $index }}-ends" type="date" wire:model="correctiveActions.{{ $index }}.ends_on" max="{{ $deadline->toDateString() }}">
                    @error("correctiveActions.{$index}.ends_on") <div class="error-text">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    @endforeach

    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <button type="button" class="btn btn-secondary" wire:click="addAction">+ Add corrective action</button>
        <div class="field" style="min-width:260px;">
            <label for="correctiveActionFiles">Corrective action documents (optional)</label>
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
