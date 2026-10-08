<section>
    <x-page-header title="Users & Roles" subtitle="Assign each person a function, a farm (Responder roles) and an approver — CARs are routed by these fields">
        <button type="button" class="btn btn-secondary" wire:click="refreshDirectory">Refresh directory</button>
    </x-page-header>

    <div class="content">
        @if ($notice)
            <div class="flash" wire:key="notice">{{ $notice }}</div>
        @endif

        @if ($showForm)
            <form wire:submit="save" class="card" style="padding:16px 18px; margin-bottom:14px;" wire:key="user-form">
                <div class="section-title">{{ $form->user ? 'Edit '.$form->user->name : 'Grant access' }}</div>

                <div class="field-grid">
                    <div class="field">
                        <label for="f-name">Full name</label>
                        <input id="f-name" wire:model="form.name" @disabled(! $form->user)>
                        @error('form.name') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label for="f-email">Email</label>
                        <input id="f-email" type="email" wire:model="form.email" @disabled(! $form->user)>
                        @if (! $form->user)
                            <div class="hint">Name and email come from the central directory.</div>
                        @endif
                        @error('form.email') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="field-grid" style="margin-top:14px;">
                    <div class="field">
                        <label for="f-role">Role</label>
                        <select id="f-role" wire:model.live="form.role">
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}">{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        @if ($selectedRole)
                            <div class="hint">{{ $selectedRole->description() }}</div>
                        @endif
                        @error('form.role') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label for="f-farm">Farm</label>
                        <select id="f-farm" wire:model.live="form.farmId" @disabled(! $selectedRole?->isFarmScoped())>
                            <option value="">{{ $selectedRole?->isFarmScoped() ? 'Select a farm…' : 'Not needed for this role' }}</option>
                            @foreach ($farms as $farm)
                                <option value="{{ $farm->id }}">{{ $farm->name }}</option>
                            @endforeach
                        </select>
                        @error('form.farmId') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="field-grid" style="margin-top:14px;">
                    <div class="field">
                        <label for="f-approver">Approver</label>
                        <select id="f-approver" wire:model="form.approverId" @disabled(! $selectedRole?->approverRole())>
                            <option value="">{{ $selectedRole?->approverRole() ? 'No approver yet' : 'Not needed for this role' }}</option>
                            @foreach ($approverOptions as $option)
                                <option value="{{ $option->id }}" wire:key="approver-{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                        @if ($selectedRole?->approverRole())
                            <div class="hint">Must be an active {{ $selectedRole->approverRole()->label() }}{{ $selectedRole->isFarmScoped() ? ' on the same farm' : '' }}.</div>
                        @endif
                        @error('form.approverId') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label for="f-central-id">Central user ID</label>
                        <input id="f-central-id" type="number" min="1" wire:model="form.centralId" disabled>
                        <div class="hint">Links this account to the central directory. They sign in with their company password.</div>
                        @error('form.centralId') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px; padding-top:14px; border-top:.5px solid var(--border);">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Cancel</button>
                    <button type="submit" class="btn btn-accent">{{ $form->user ? 'Save changes' : 'Grant access' }}</button>
                </div>
            </form>
        @endif

        <div class="card" style="margin-bottom:14px;">
            <div style="padding:14px 18px 10px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <div class="section-title" style="margin:0;">Central directory</div>
                    <div style="font-size:11px; color:var(--text3); margin-top:2px;">
                        Everyone at bfcgroup.ph · {{ $directoryTotal }} people @if ($search !== '') · {{ $directoryMatches }} {{ str('match')->plural($directoryMatches) }} @endif — grant access to give someone a role in this system.
                    </div>
                </div>
                <div class="field" style="min-width:260px;">
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or email…" aria-label="Search the directory">
                </div>
            </div>

            @if ($directoryError)
                <div class="flash" style="margin:0 18px 14px; background:var(--red-bg); color:var(--red); border-color:var(--red-bd);">{{ $directoryError }}</div>
            @endif
            @if ($undecryptable > 0)
                <div class="flash" style="margin:0 18px 14px;">
                    {{ $undecryptable }} directory {{ str('record')->plural($undecryptable) }} could not be read: their ids did not decrypt. This system's APP_KEY must match the central system's.
                </div>
            @endif

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th>Person</th><th>Central ID</th><th>Access</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($directory as $person)
                            @php $local = $localById->get($person['id']); @endphp
                            <tr wire:key="directory-{{ $person['id'] }}">
                                <td>{{ $person['name'] }}<div style="font-size:10px; color:var(--text3);">{{ $person['email'] }}</div></td>
                                <td class="tnum">{{ $person['id'] }}</td>
                                <td>
                                    @if (! $local)
                                        <x-pill tone="slate">No access</x-pill>
                                    @elseif (! $local->is_active)
                                        <x-pill tone="slate">Deactivated</x-pill>
                                    @else
                                        <x-pill :tone="$local->role->tone()">{{ $local->roleWithScope() }}</x-pill>
                                    @endif
                                </td>
                                <td style="text-align:right;">
                                    <button type="button" class="btn {{ $local ? 'btn-ghost' : 'btn-accent' }}" wire:click="grant({{ $person['id'] }})">{{ $local ? 'Edit' : 'Grant access' }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-row">{{ $directoryError ? 'The directory could not be loaded.' : ($search !== '' ? 'No one matches that search.' : 'The directory is empty.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($directoryMatches > $directory->count())
                <div style="padding:8px 18px 12px; font-size:11px; color:var(--text3);">Showing the first {{ $directory->count() }} — search to narrow it down.</div>
            @endif
        </div>

        <div class="card">
            <div style="padding:14px 18px 4px;"><div class="section-title" style="margin:0;">Accounts with access</div></div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th>User</th><th>Role</th><th>Farm</th><th>Approver</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr wire:key="user-{{ $user->id }}">
                                <td>
                                    <div style="display:flex; align-items:center; gap:9px;">
                                        <x-role-avatar :user="$user" class="role-avatar" style="width:26px; height:26px; font-size:10.5px;" />
                                        <div>
                                            <div @style(['color:var(--text3); text-decoration:line-through' => ! $user->is_active])>{{ $user->name }}</div>
                                            <div style="font-size:10px; color:var(--text3);">{{ $user->email }} · ID {{ $user->id }}@if ($user->is_sample) · sample @endif</div>
                                        </div>
                                    </div>
                                </td>
                                <td><x-pill :tone="$user->role->tone()">{{ $user->role->label() }}</x-pill></td>
                                <td>{{ $user->farm?->name ?? '—' }}</td>
                                <td>{{ $user->approver?->name ?? '—' }}</td>
                                <td>
                                    <x-pill :tone="$user->is_active ? 'green' : 'slate'">{{ $user->is_active ? 'Active' : 'Deactivated' }}</x-pill>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <button type="button" class="btn btn-ghost" wire:click="edit({{ $user->id }})">Edit</button>
                                    @unless ($user->is(auth()->user()))
                                        <button type="button" class="btn btn-ghost" wire:click="toggleActive({{ $user->id }})">{{ $user->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <footer class="note">Accounts are deactivated, never deleted, so CAR history keeps their names. Escalation for approver-prepared responses (Step 10) is still an open decision.</footer>
    </div>
</section>
