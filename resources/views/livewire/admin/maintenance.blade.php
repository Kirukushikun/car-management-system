<section>
    @php
        [$singular, $plural] = $tabs[$tab];
        $byLine = in_array($tab, ['units', 'categories', 'subcategories'], true);
    @endphp
    <x-page-header title="Maintenance" subtitle="The values offered in the CAR form — add, rename or deactivate farms, units, categories, sub-categories and types of complaint">
        <button type="button" class="btn btn-accent" wire:click="create"><x-icon name="plus" /> Add {{ $singular }}</button>
    </x-page-header>

    <div class="content">
        <nav class="maint-tabs" aria-label="Values">
            @foreach ($tabs as $key => [, $label])
                <button type="button" @class(['maint-tab', 'is-active' => $tab === $key]) wire:click="$set('tab', '{{ $key }}')" wire:key="tab-{{ $key }}"
                        @if ($tab === $key) aria-current="page" @endif>{{ $label }}</button>
            @endforeach
        </nav>

        @if ($notice)
            <div class="flash" wire:key="notice">{{ $notice }}</div>
        @endif

        @if ($showForm)
            <form wire:submit="save" class="card" style="padding:16px 18px; margin-bottom:14px;" wire:key="maintenance-form-{{ $tab }}-{{ $editingId ?? 'new' }}">
                <div class="section-title">{{ $editing ? 'Edit '.$singular.' — '.$editing->name : 'Add '.$singular }}</div>

                <div class="field-grid">
                    <div class="field">
                        <label for="m-name">Name</label>
                        <input id="m-name" wire:model="name" maxlength="{{ \App\Support\TextLimits::SHORT }}" autofocus>
                        @if ($editing)
                            <div class="hint">Renaming changes it everywhere, including CARs already issued.</div>
                        @endif
                        @error('name') <div class="error-text">{{ $message }}</div> @enderror
                    </div>

                    @if ($byLine)
                        <div class="field">
                            <label for="m-line">Business line</label>
                            <select id="m-line" wire:model.live="businessLineId" @disabled($locked)>
                                @foreach ($lines as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                            @if ($locked)
                                <div class="hint">Fixed — it is already in use.</div>
                            @endif
                            @error('businessLineId') <div class="error-text">{{ $message }}</div> @enderror
                        </div>
                    @endif
                </div>

                @if ($tab === 'categories')
                    <div class="field-grid" style="margin-top:14px;">
                        <div class="field">
                            <label for="m-response">Response (days)</label>
                            <input id="m-response" type="number" min="1" max="60" wire:model="responseDays">
                            @error('responseDays') <div class="error-text">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="m-implementation">CA implementation (days)</label>
                            <input id="m-implementation" type="number" min="1" max="90" wire:model="implementationDays">
                            <div class="hint">Counted from the issued date. CARs already issued keep their deadlines.</div>
                            @error('implementationDays') <div class="error-text">{{ $message }}</div> @enderror
                        </div>
                    </div>
                @endif

                @if ($tab === 'subcategories')
                    <div class="field-grid" style="margin-top:14px;">
                        <div class="field">
                            <label for="m-category">Category</label>
                            <select id="m-category" wire:model="categoryId" @disabled($locked)>
                                <option value="">Select a category…</option>
                                @foreach ($categoryOptions as $option)
                                    <option value="{{ $option->id }}" wire:key="category-option-{{ $option->id }}">{{ $option->name }}{{ $option->is_active ? '' : ' (inactive)' }}</option>
                                @endforeach
                            </select>
                            @error('categoryId') <div class="error-text">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="m-description">What to report</label>
                            <textarea id="m-description" rows="3" wire:model="description" maxlength="{{ \App\Support\TextLimits::LONG }}" placeholder="Examples shown in the form's guide"></textarea>
                            @error('description') <div class="error-text">{{ $message }}</div> @enderror
                        </div>
                    </div>
                @endif

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px; padding-top:14px; border-top:.5px solid var(--border);">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Cancel</button>
                    <button type="submit" class="btn btn-accent">{{ $editing ? 'Save changes' : 'Add '.$singular }}</button>
                </div>
            </form>
        @endif

        <div class="card">
            <div class="maint-head">
                <div class="section-title" style="margin:0;">{{ $plural }}</div>
                <span class="maint-count">{{ $records->where('is_active', true)->count() }} active · {{ $records->where('is_active', false)->count() }} inactive</span>
                @if ($byLine)
                    <select wire:model.live="line" aria-label="Business line" class="maint-filter">
                        <option value="">All business lines</option>
                        @foreach ($lines as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            @if ($tab === 'subcategories') <th>Category</th> @endif
                            @if ($byLine) <th>Business line</th> @endif
                            @if ($tab === 'categories') <th>Response</th><th>Implementation</th> @endif
                            <th>Used by</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            @php $used = $usage[$record->id]; @endphp
                            <tr wire:key="{{ $tab }}-{{ $record->id }}" @class(['is-inactive' => ! $record->is_active])>
                                <td>
                                    <span class="maint-name">{{ $record->name }}</span>
                                    @if ($tab === 'subcategories' && $record->description)
                                        <div class="maint-sub">{{ \Illuminate\Support\Str::limit($record->description, 110) }}</div>
                                    @endif
                                </td>
                                @if ($tab === 'subcategories')
                                    <td>{{ $record->category->name }}@unless ($record->category->is_active) <span class="maint-sub">(inactive category)</span>@endunless</td>
                                @endif
                                @if ($byLine)
                                    <td>{{ ($tab === 'subcategories' ? $record->category->businessLine : $record->businessLine)->name }}</td>
                                @endif
                                @if ($tab === 'categories')
                                    <td class="tnum">{{ $record->response_days }} {{ str('day')->plural($record->response_days) }}</td>
                                    <td class="tnum">{{ $record->implementation_days }} {{ str('day')->plural($record->implementation_days) }}</td>
                                @endif
                                <td class="maint-usage">
                                    @if ($used['total'] === 0)
                                        <span style="color:var(--text3);">Not used</span>
                                    @else
                                        {{ collect([
                                            $used['cars'] ? $used['cars'].' '.str('CAR')->plural($used['cars']) : null,
                                            $used['users'] ? $used['users'].' '.str('user')->plural($used['users']) : null,
                                            $used['subcategories'] ? $used['subcategories'].' '.str('sub-category')->plural($used['subcategories']) : null,
                                        ])->filter()->join(' · ') }}
                                    @endif
                                </td>
                                <td><x-pill :tone="$record->is_active ? 'green' : 'slate'">{{ $record->is_active ? 'Active' : 'Inactive' }}</x-pill></td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <button type="button" class="btn btn-ghost" wire:click="edit({{ $record->id }})">Edit</button>
                                    @if ($record->is_active)
                                        <button type="button" class="btn btn-ghost"
                                                x-on:click="$dispatch('confirm', { ...@js(['title' => 'Deactivate "'.$record->name.'"?', 'message' => 'It will no longer be offered on new CARs. CARs that already have it keep it, and you can reactivate it any time.', 'confirmLabel' => 'Deactivate']), run: () => $wire.toggleActive({{ $record->id }}) })">Deactivate</button>
                                    @else
                                        <button type="button" class="btn btn-ghost" wire:click="toggleActive({{ $record->id }})">Reactivate</button>
                                    @endif
                                    @if ($used['total'] === 0)
                                        <button type="button" class="btn btn-ghost" style="color:var(--red);"
                                                x-on:click="$dispatch('confirm', { ...@js(['title' => 'Delete "'.$record->name.'"?', 'message' => 'Nothing uses it yet, so it can be removed for good.', 'confirmLabel' => 'Delete', 'danger' => true]), run: () => $wire.delete({{ $record->id }}) })">Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="empty-row">No {{ strtolower($plural) }} yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <footer class="note">Values in use are deactivated, never deleted, so issued CARs keep showing them. Delete appears only for values nothing uses yet. Every change is recorded in the Audit Log.</footer>
    </div>
</section>
