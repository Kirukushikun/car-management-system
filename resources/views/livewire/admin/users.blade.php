<section>
    <x-page-header title="Users & Roles" subtitle="Assign each person a function, a farm (Responder roles) and an approver — CARs are routed by these fields">
        @unless ($showForm)
            <button type="button" class="btn btn-accent" wire:click="create">+ Add user</button>
        @endunless
    </x-page-header>

    <div class="content">
        @if ($notice)
            <div class="flash" wire:key="notice">{{ $notice }}</div>
        @endif

        @if ($showForm)
            <form wire:submit="save" class="card" style="padding:16px 18px; margin-bottom:14px;" wire:key="user-form">
                <div class="section-title">{{ $form->user ? 'Edit '.$form->user->name : 'Add user' }}</div>

                <div class="field-grid">
                    <div class="field">
                        <label for="f-name">Full name</label>
                        <input id="f-name" wire:model="form.name" placeholder="e.g. Juan Dela Cruz">
                        @error('form.name') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label for="f-email">Email</label>
                        <input id="f-email" type="email" wire:model="form.email">
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
                        <label for="f-password">{{ $form->user ? 'New password (leave blank to keep)' : 'Initial password' }}</label>
                        <input id="f-password" type="password" wire:model="form.password" autocomplete="new-password">
                        @error('form.password') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px; padding-top:14px; border-top:.5px solid var(--border);">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Cancel</button>
                    <button type="submit" class="btn btn-accent">{{ $form->user ? 'Save changes' : 'Create user' }}</button>
                </div>
            </form>
        @endif

        <div class="card">
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
                                            <div style="font-size:10px; color:var(--text3);">{{ $user->email }}</div>
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
