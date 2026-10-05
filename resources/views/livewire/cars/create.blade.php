<section>
    <x-page-header title="New CAR — Phase I: Initiation & Submission"
                   subtitle="Reference number is generated automatically on submit — the Requestor Approver reviews it before it is released to the Responder" />

    <div class="content">
        <form wire:submit="submit" class="card" style="padding:20px 22px; max-width:760px;">
            @if ($notice)
                <div class="flash">{{ $notice }}</div>
            @endif

            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
                <span class="pill" style="background:var(--bg2); color:var(--text2); border:.5px solid var(--border2);">CAR reference: <span class="tnum" style="margin-left:4px;">{{ $reference }}</span></span>
                <span class="pill" style="background:var(--bg2); color:var(--text2); border:.5px solid var(--border2);">Issued date: <span class="tnum" style="margin-left:4px;">{{ $issuedOn->format('M j, Y') }}</span></span>
            </div>

            <div class="field-grid">
                <div class="field">
                    <label for="farmId">Farm / site</label>
                    <select id="farmId" wire:model="farmId">
                        @foreach ($farms as $farm)
                            <option value="{{ $farm->id }}" wire:key="farm-{{ $farm->id }}">{{ $farm->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="unitId">Issued to (operational unit)</label>
                    <select id="unitId" wire:model.live="unitId">
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}" wire:key="unit-{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    <div class="hint">Business line: {{ $line }}</div>
                </div>
            </div>

            <div class="field-grid" style="margin-top:14px;">
                <div class="field">
                    <label for="categoryId">Category</label>
                    <select id="categoryId" wire:model.live="categoryId">
                        @foreach ($categories as $option)
                            <option value="{{ $option->id }}" wire:key="category-{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    @error('categoryId') <div class="error-text">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="subcategoryId">Sub-category</label>
                    <select id="subcategoryId" wire:model.live="subcategoryId">
                        @foreach ($subcategories as $option)
                            <option value="{{ $option->id }}" wire:key="subcategory-{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    @error('subcategoryId') <div class="error-text">{{ $message }}</div> @enderror
                </div>
            </div>
            @if ($subcategory?->description)
                <div class="hint" style="margin-top:6px; font-size:10.5px; color:var(--text3);">What to report here: {{ $subcategory->description }}</div>
            @endif

            <div class="field-grid" style="margin-top:14px;">
                <div class="field">
                    <label for="issuedBy">Issued by</label>
                    <input id="issuedBy" wire:model="issuedBy">
                </div>
                <div class="field">
                    <label for="complainant">Complainant</label>
                    <input id="complainant" wire:model="complainant" placeholder="e.g. customer name, distributor, internal audit">
                    @error('complainant') <div class="error-text">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="surface deadline-preview" style="margin-top:16px;">
                <div class="box">
                    <div class="lbl">Response deadline (auto)</div>
                    <div class="date tnum">{{ $responseDue?->format('M j, Y') ?? '—' }} (+{{ $category?->response_days }}d)</div>
                </div>
                <div class="box" style="border-left:.5px solid var(--border);">
                    <div class="lbl">Implementation deadline (auto)</div>
                    <div class="date tnum">{{ $implementationDue?->format('M j, Y') ?? '—' }} (+{{ $category?->implementation_days }}d)</div>
                </div>
            </div>
            <div class="hint" style="margin-top:6px; font-size:10.5px; color:var(--text3);">Computed from Issued Date + the Response / CA Implementation Timeline for the selected category.</div>

            <div class="field" style="margin-top:16px;">
                <label for="problem">Details of the problem</label>
                <textarea id="problem" wire:model="problem" placeholder="Describe what was observed, when, and by whom..."></textarea>
                @error('problem') <div class="error-text">{{ $message }}</div> @enderror
            </div>

            <div class="field" style="margin-top:14px;">
                <label>File attachments (optional)</label>
                <div class="dropzone">Photos, videos or documents supporting the complaint — uploads arrive in Phase 3</div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; padding-top:16px; border-top:.5px solid var(--border);">
                <a href="{{ route('cars.index') }}" wire:navigate class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
                <button type="submit" class="btn btn-accent">Submit CAR</button>
            </div>
        </form>
    </div>
</section>
