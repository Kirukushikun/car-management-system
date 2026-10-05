<section>
    <x-page-header :title="$car ? 'Correct '.$car->reference.' — Phase I' : 'New CAR — Phase I: Initiation & Submission'"
                   :subtitle="$car ? 'Fix what the Requestor Approver asked for, then resubmit it for release' : 'The reference number is assigned on submit — the Requestor Approver reviews the CAR before it is released to the Responder'" />

    <div class="content">
        <form wire:submit="submit" class="card" style="padding:20px 22px; max-width:760px;">
            @if ($returnReason)
                <div class="flash" style="background:var(--red-bg); color:var(--red); border-color:var(--red-bd);">
                    Returned by the Requestor Approver: {{ $returnReason }}
                </div>
            @endif

            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
                <span class="pill" style="background:var(--bg2); color:var(--text2); border:.5px solid var(--border2);">CAR reference: <span class="tnum" style="margin-left:4px;">{{ $car?->reference ?? 'assigned on submit' }}</span></span>
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
                    <div class="hint">The farm whose Responders must answer this CAR.</div>
                    @error('farmId') <div class="error-text">{{ $message }}</div> @enderror
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
                    <label for="complaintType">Type of complaint</label>
                    <select id="complaintType" wire:model="complaintType">
                        @foreach ($complaintTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('complaintType') <div class="error-text">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="complaintReceivedOn">Complaint received on (optional)</label>
                    <input id="complaintReceivedOn" type="date" wire:model="complaintReceivedOn" max="{{ now()->toDateString() }}">
                    <div class="hint">When the customer first reported it, if earlier than today.</div>
                    @error('complaintReceivedOn') <div class="error-text">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="field-grid" style="margin-top:14px;">
                <div class="field">
                    <label for="issuedBy">Issued by</label>
                    <input id="issuedBy" wire:model="issuedBy">
                    @error('issuedBy') <div class="error-text">{{ $message }}</div> @enderror
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
            <div class="hint" style="margin-top:6px; font-size:10.5px; color:var(--text3);">Issued date + the category's Response / CA Implementation timeline. Fixed when the CAR is issued.</div>

            <div class="field" style="margin-top:16px;">
                <label for="problem">Details of the problem</label>
                <textarea id="problem" wire:model="problem" placeholder="Describe what was observed, when, and by whom..."></textarea>
                @error('problem') <div class="error-text">{{ $message }}</div> @enderror
            </div>

            <div class="field" style="margin-top:14px;">
                <label for="attachments">File attachments (optional)</label>
                @if ($existingAttachments->isNotEmpty())
                    <div style="display:flex; flex-direction:column; gap:4px; margin-bottom:8px;">
                        @foreach ($existingAttachments as $existing)
                            <a href="{{ route('attachments.show', $existing) }}" target="_blank" style="font-size:11.5px;" wire:key="existing-{{ $existing->id }}">{{ $existing->original_name }} · {{ $existing->humanSize() }}</a>
                        @endforeach
                    </div>
                @endif
                <label class="dropzone" style="display:block; cursor:pointer; text-transform:none; letter-spacing:0; font-weight:400;">
                    <input id="attachments" type="file" wire:model="attachments" multiple hidden
                           accept=".{{ implode(',.', \App\Models\Attachment::ALLOWED_EXTENSIONS) }}">
                    <span wire:loading.remove wire:target="attachments">Click to add photos, videos (mp4, mov), screenshots, PDF or Office files — up to 10 files, 50 MB each</span>
                    <span wire:loading wire:target="attachments">Uploading…</span>
                </label>
                @if ($attachments)
                    <div style="display:flex; flex-direction:column; gap:4px; margin-top:8px;">
                        @foreach ($attachments as $index => $file)
                            <div style="display:flex; justify-content:space-between; gap:10px; font-size:11.5px;" wire:key="upload-{{ $index }}">
                                <span>{{ $file->getClientOriginalName() }}</span>
                                <button type="button" class="btn btn-ghost" style="padding:0 6px;" wire:click="removeAttachment({{ $index }})">Remove</button>
                            </div>
                            @error("attachments.{$index}") <div class="error-text">{{ $message }}</div> @enderror
                        @endforeach
                    </div>
                @endif
                @error('attachments') <div class="error-text">{{ $message }}</div> @enderror
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; padding-top:16px; border-top:.5px solid var(--border);">
                <a href="{{ $car ? route('cars.show', $car) : route('cars.index') }}" wire:navigate class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
                <button type="submit" class="btn btn-accent" wire:loading.attr="disabled" wire:target="submit,attachments">{{ $car ? 'Resubmit for release' : 'Submit CAR' }}</button>
            </div>
        </form>
    </div>
</section>
