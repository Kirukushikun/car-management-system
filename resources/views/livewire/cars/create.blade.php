<section>
    <x-page-header :title="$car ? 'Correct '.$car->reference.' — Phase I' : 'New CAR — Phase I: Initiation & Submission'"
                   :subtitle="$car ? 'Fix what the Complainant Approver asked for, then resubmit it for release' : 'The reference number is assigned on submit — the Complainant Approver reviews the CAR before it is released to the Concerned Department'">
        <x-guide-button name="phase-one" />
    </x-page-header>

    <div class="content">
        <form x-on:submit.prevent class="card" style="padding:20px 22px; max-width:760px;">
            @if ($returnReason)
                <div class="flash" style="background:var(--red-bg); color:var(--red); border-color:var(--red-bd);">
                    Returned by the Complainant Approver: {{ $returnReason }}
                </div>
            @endif

            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
                <span class="pill" style="background:var(--bg2); color:var(--text2); border:.5px solid var(--border2);">CAR reference: <span class="tnum" style="margin-left:4px;">{{ $car?->reference ?? 'assigned on submit' }}</span></span>
                <span class="pill" style="background:var(--bg2); color:var(--text2); border:.5px solid var(--border2);">Issued date: <span class="tnum" style="margin-left:4px;">{{ $issuedOn->format('M j, Y') }}</span></span>
            </div>

            <div class="field-grid">
                <div class="field">
                    <x-field-label for="farmId" tip="The farm whose Concerned Department must answer this CAR.">Farm / site</x-field-label>
                    <select id="farmId" wire:model="farmId">
                        @foreach ($farms as $farm)
                            <option value="{{ $farm->id }}" wire:key="farm-{{ $farm->id }}">{{ $farm->name }}</option>
                        @endforeach
                    </select>
                    @error('farmId') <div class="error-text">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <x-field-label tip="The operational units the problem concerns. Pick one or more, all from one business line — the line decides which categories you can pick.">Issued to (operational units)</x-field-label>
                    <x-multi-select model="unitIds" :options="$unitOptions" placeholder="Select one or more units" />
                    <div class="hint">Business line: {{ $line ?? '—' }}</div>
                    @error('unitIds') <div class="error-text">{{ $message }}</div> @enderror
                    @error('unitIds.*') <div class="error-text">{{ $message }}</div> @enderror
                </div>
            </div>

            @foreach ($pairs as $index => $pair)
                @php($pairCategory = $categories->firstWhere('id', (int) $pair['category_id']))
                <div class="field-grid pair-row" style="margin-top:14px;" wire:key="pair-{{ $index }}-{{ $pair['category_id'] }}">
                    <div class="field">
                        <x-field-label :for="'pair-'.$index.'-category'" tip="The category the problem falls under. Each category can be used once — use “+ Add category” if it also falls under another. Not sure which? See Guide → Categories (top right).">Category</x-field-label>
                        <select id="pair-{{ $index }}-category" wire:model.live="pairs.{{ $index }}.category_id">
                            @foreach ($categories as $option)
                                @if ($option->id === (int) $pair['category_id'] || ! in_array($option->id, $usedCategoryIds, true))
                                    <option value="{{ $option->id }}" wire:key="pair-{{ $index }}-category-{{ $option->id }}">{{ $option->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        @error("pairs.{$index}.category_id") <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <x-field-label tip="One or more sub-categories of this category. Guide → Categories explains what to report under each.">
                            Sub-category
                            @if (count($pairs) > 1)
                                <x-slot:actions>
                                    <button type="button" class="btn btn-ghost pair-remove" wire:click="removePair({{ $index }})">Remove pair</button>
                                </x-slot:actions>
                            @endif
                        </x-field-label>
                        <x-multi-select :model="'pairs.'.$index.'.subcategory_ids'" placeholder="Select one or more sub-categories"
                                        :options="($pairCategory?->subcategories ?? collect())->map(fn ($subcategory): array => ['value' => $subcategory->id, 'label' => $subcategory->name, 'group' => null])->all()" />
                        @error("pairs.{$index}.subcategory_ids") <div class="error-text">{{ $message }}</div> @enderror
                        @error("pairs.{$index}.subcategory_ids.*") <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                </div>
            @endforeach
            @error('pairs') <div class="error-text">{{ $message }}</div> @enderror

            <div class="pair-actions">
                @if (count($usedCategoryIds) < $categories->count())
                    <button type="button" class="btn btn-secondary" wire:click="addPair"><x-icon name="plus" /> Add category</button>
                @endif
            </div>

            <div class="field-grid" style="margin-top:14px;">
                <div class="field">
                    <x-field-label tip="Product Nonconformity, Service Nonconformity or Others — pick all that apply.">Type of complaint</x-field-label>
                    <x-multi-select model="complaintTypes" :options="$complaintTypeOptions" placeholder="Select one or more types" />
                    @error('complaintTypes') <div class="error-text">{{ $message }}</div> @enderror
                    @error('complaintTypes.*') <div class="error-text">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <x-field-label for="complaintReceivedOn" tip="When the customer first reported it, if earlier than today. Leave blank if it was today.">Complaint received on (optional)</x-field-label>
                    <input id="complaintReceivedOn" type="date" wire:model="complaintReceivedOn" max="{{ now()->toDateString() }}">
                    @error('complaintReceivedOn') <div class="error-text">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="field-grid" style="margin-top:14px;">
                <div class="field">
                    <x-field-label for="issuedBy" tip="Filled in with your name and role. Change it only if you are filing for someone else.">Issued by</x-field-label>
                    <input id="issuedBy" wire:model="issuedBy">
                    @error('issuedBy') <div class="error-text">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <x-field-label for="complainant" tip="Who raised the problem — for example a customer, a distributor or internal audit.">Complainant</x-field-label>
                    <input id="complainant" wire:model="complainant" placeholder="e.g. customer name, distributor, internal audit">
                    @error('complainant') <div class="error-text">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="surface deadline-preview" style="margin-top:16px;">
                <div class="box">
                    <div class="lbl">Response deadline (auto)</div>
                    <div class="date tnum">{{ $deadline ? $deadline['response_due_on']->format('M j, Y').' (+'.$deadline['response_days'].'d)' : '—' }}</div>
                </div>
                <div class="box" style="border-left:.5px solid var(--border);">
                    <div class="lbl">Implementation deadline (auto)</div>
                    <div class="date tnum">{{ $deadline ? $deadline['implementation_due_on']->format('M j, Y').' (+'.$deadline['implementation_days'].'d)' : '—' }}</div>
                </div>
            </div>
            <div class="hint" style="margin-top:6px; font-size:10.5px; color:var(--text3);">Issued date + the category's timeline{{ count($usedCategoryIds) > 1 ? ' — with several categories, the shortest applies' : '' }}. Fixed once the CAR is issued.</div>

            <div class="field" style="margin-top:16px;">
                <x-field-label for="problem" tip="What was observed, when, where and by whom. Be specific — the Concerned Department works from this.">Details of the problem</x-field-label>
                <textarea id="problem" wire:model="problem" placeholder="Describe what was observed, when, and by whom..."></textarea>
                @error('problem') <div class="error-text">{{ $message }}</div> @enderror
            </div>

            <div class="field" style="margin-top:14px;">
                <x-field-label for="attachments" tip="Optional proof: photos, videos (mp4, mov), PDF or Office files — up to 10 files, 50 MB each.">File attachments (optional)</x-field-label>
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

            @error('submission') <div class="flash" style="background:var(--red-bg); color:var(--red); border-color:var(--red-bd); margin:18px 0 0;" role="alert">{{ $message }}</div> @enderror

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; padding-top:16px; border-top:.5px solid var(--border);">
                <a href="{{ $car ? route('cars.show', $car) : route('cars.index') }}" wire:navigate class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
                @php($submitAction = $car ? \App\Enums\CarAction::Resubmit : \App\Enums\CarAction::Submit)
                <button type="button" class="btn btn-accent" wire:loading.attr="disabled" wire:target="submit,attachments"
                        x-on:click="$dispatch('confirm', { ...@js(['title' => $submitAction->label().'?', 'message' => $submitAction->confirmation(), 'confirmLabel' => $submitAction->label()]), run: () => $wire.submit() })">{{ $submitAction->label() }}</button>
            </div>
        </form>
    </div>

    <x-guides.phase-one :lines="$guideLines" :current="$line" />
</section>
