<section>
    <x-page-header title="Users & Roles" subtitle="Assign each person a function and the farm or unit they belong to — CARs are routed by these two fields" />

    <div class="content">
        @if ($notice)
            <div class="flash">{{ $notice }}</div>
        @endif

        <form wire:submit="stub('Adding a user')" class="card" style="padding:16px 18px; margin-bottom:14px;">
            <div class="section-title">Add user</div>
            <div class="field-grid" style="grid-template-columns:2fr 2fr 1.4fr 1.2fr auto; align-items:end;">
                <div class="field"><label for="u-name">Full name</label><input id="u-name" placeholder="e.g. Juan Dela Cruz"></div>
                <div class="field"><label for="u-email">Email</label><input id="u-email" type="email" placeholder="name@company"></div>
                <div class="field">
                    <label for="u-role">Role</label>
                    <select id="u-role">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="u-scope">Farm / unit</label>
                    <select id="u-scope">
                        @foreach ($scopes as $scope)
                            <option>{{ $scope }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-accent" style="margin-bottom:1px;">Add user</button>
            </div>
        </form>

        <div class="card">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th>User</th><th>Role</th><th>Farm / unit</th><th>Approver</th><th>Status</th><th></th></tr>
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
                                <td>{{ $user->scope ?? '—' }}</td>
                                <td>{{ $user->approver?->name ?? '—' }}</td>
                                <td>
                                    <x-pill :tone="$user->is_active ? 'green' : 'slate'">{{ $user->is_active ? 'Active' : 'Deactivated' }}</x-pill>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <button type="button" class="btn btn-ghost" wire:click="stub('Editing a user')">Edit</button>
                                    <button type="button" class="btn btn-ghost" wire:click="stub('{{ $user->is_active ? 'Deactivating' : 'Reactivating' }} a user')">{{ $user->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <footer class="note">Accounts are real; changes are stubs until Phase 1. Planned: approver chain per Responder, deactivate instead of delete (CAR history keeps names), and an audit log of every change.</footer>
    </div>
</section>
