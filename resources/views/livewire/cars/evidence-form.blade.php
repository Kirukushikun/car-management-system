<form x-on:submit.prevent id="evidence-form" class="embedded-form">
    <div class="embedded-form-head">
        <strong>Your implementation evidence — solution {{ $round->number }}</strong>
        <span>Step 11 · due {{ $deadline->format('M j, Y') }}</span>
    </div>
    @error('evidence') <div class="flash" style="background:var(--red-bg); color:var(--red); border-color:var(--red-bd); margin-bottom:0;">{{ $message }}</div> @enderror

    <div class="field">
        <x-field-label for="evidenceFiles" tip="Proof that each corrective action is in place — photos, videos, checklists, logs, training records. At least one file; up to 10 files, 50 MB each.">Files and photos proving the corrective actions were carried out</x-field-label>
        <label class="dropzone" style="display:block; cursor:pointer; text-transform:none; letter-spacing:0; font-weight:400;">
            <input id="evidenceFiles" type="file" wire:model="files" multiple hidden accept=".{{ implode(',.', \App\Models\Attachment::ALLOWED_EXTENSIONS) }}">
            <span wire:loading.remove wire:target="files">Click to add photos, videos, checklists, logs — up to 10 files, 50 MB each</span>
            <span wire:loading wire:target="files">Uploading…</span>
        </label>
        @foreach ($files as $index => $file)
            <div style="display:flex; justify-content:space-between; gap:10px; font-size:11.5px; margin-top:6px;" wire:key="evidence-upload-{{ $index }}">
                <span>{{ $file->getClientOriginalName() }}</span>
                <button type="button" class="btn btn-ghost" style="padding:0 6px;" wire:click="removeFile({{ $index }})">Remove</button>
            </div>
            @error("files.{$index}") <div class="error-text">{{ $message }}</div> @enderror
        @endforeach
        @error('files') <div class="error-text">{{ $message }}</div> @enderror
    </div>

    @if ($existingFiles->isNotEmpty())
        <div class="kv">
            <span class="k">Already attached to this round</span>
            @foreach ($existingFiles as $existing)
                <a href="{{ route('attachments.show', $existing) }}" target="_blank" style="font-size:11.5px;" wire:key="evidence-existing-{{ $existing->id }}">{{ $existing->original_name }} <span style="color:var(--text3);">· {{ $existing->humanSize() }}</span></a>
            @endforeach
        </div>
    @endif

    <div class="field-grid">
        <div class="field">
            <x-field-label for="evidenceResponsible" tip="Who carried out the corrective actions or gathered this evidence.">Responsible person</x-field-label>
            <input id="evidenceResponsible" maxlength="{{ \App\Support\TextLimits::SHORT }}" wire:model="responsible">
            <x-char-count />
            @error('responsible') <div class="error-text">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <x-field-label tip="Set automatically to today when you submit.">Upload date</x-field-label>
            <input value="{{ now()->format('M j, Y') }}" disabled>
        </div>
    </div>

    <div class="field">
        <x-field-label for="evidenceNotes" tip="Optional — what was implemented, where, and how it was checked. Helps the Concerned Department Approver judge whether it worked.">Notes (optional)</x-field-label>
        <x-format-toolbar />
        <textarea id="evidenceNotes" maxlength="{{ \App\Support\TextLimits::LONG }}" wire:model="notes" style="min-height:60px;" placeholder="What was implemented, where, and how it was checked..."></textarea>
        <x-char-count />
        @error('notes') <div class="error-text">{{ $message }}</div> @enderror
    </div>

    @error('submission') <div class="flash" style="background:var(--red-bg); color:var(--red); border-color:var(--red-bd); margin:0;" role="alert">{{ $message }}</div> @enderror

    <div style="display:flex; justify-content:flex-end; padding-top:12px; border-top:.5px solid var(--border);">
        <button type="button" class="btn btn-accent" wire:loading.attr="disabled" wire:target="submit,files"
                x-on:click="$dispatch('confirm', { ...@js(['title' => 'Submit the evidence for the effectiveness check?', 'message' => \App\Enums\CarAction::UploadEvidence->confirmation(), 'confirmLabel' => 'Submit evidence']), run: () => $wire.submit() })">Submit evidence for the effectiveness check</button>
    </div>
</form>
