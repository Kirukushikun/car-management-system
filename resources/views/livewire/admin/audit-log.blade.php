<section>
    <x-page-header title="Audit Log" subtitle="Every change to users, roles, categories and sub-categories — who made it and what changed" />

    <div class="content">
        <div class="card">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th>When</th><th>By</th><th>Record</th><th>Change</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($audits as $audit)
                            <tr wire:key="audit-{{ $audit->id }}">
                                <td class="tnum" style="white-space:nowrap;">{{ $audit->created_at->format('M j, Y g:i A') }}</td>
                                <td>{{ $audit->user?->name ?? 'System' }}</td>
                                <td>
                                    {{ str($audit->auditable_type)->headline() }}
                                    <div style="font-size:10.5px; color:var(--text3);">{{ $audit->auditable?->name ?? '#'.$audit->auditable_id }}</div>
                                </td>
                                <td>
                                    <x-pill :tone="$audit->event === 'created' ? 'green' : 'blue'">{{ $audit->event }}</x-pill>
                                    <div style="font-size:11px; margin-top:4px; display:flex; flex-direction:column; gap:2px;">
                                        @foreach ($audit->new_values ?? [] as $field => $value)
                                            <span>
                                                <span style="color:var(--text3);">{{ str($field)->headline() }}:</span>
                                                @if ($audit->event === 'updated')
                                                    <span style="text-decoration:line-through; color:var(--text3);">{{ is_scalar($audit->old_values[$field] ?? null) ? ($audit->old_values[$field] ?? '—') : json_encode($audit->old_values[$field] ?? null) }}</span> →
                                                @endif
                                                {{ is_scalar($value) ? ($value === true ? 'yes' : ($value === false ? 'no' : $value)) : json_encode($value) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-row">No changes recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top:12px;">{{ $audits->links() }}</div>

        <footer class="note">Backups run nightly at 01:00 (php artisan app:backup) into storage/app/backups and are kept 14 days — copy that folder off the server with the server's own backup routine.</footer>
    </div>
</section>
