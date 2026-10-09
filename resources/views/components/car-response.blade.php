@props(['response', 'showRound' => false, 'outcome' => null])

@use('App\Models\Attachment')

<div {{ $attributes->merge(['style' => 'display:flex; flex-direction:column; gap:12px;']) }}>
    @if ($showRound)
        <x-solution-badge :number="$response->round->number" :outcome="$outcome" />
    @endif

    <div class="kv">
        <span class="k">Interim containment
            @if ($response->containment_starts_on)
                · {{ $response->containment_starts_on->format('M j') }} – {{ $response->containment_ends_on?->format('M j, Y') }}
            @endif
            @if ($response->containment_responsible) · {{ $response->containment_responsible }} @endif
        </span>
        <span class="v" style="white-space:pre-line; font-weight:400;">{{ $response->containment_actions ?? '—' }}</span>
    </div>

    <div class="kv">
        <span class="k">Root cause @if ($response->root_cause_responsible) · {{ $response->root_cause_responsible }} @endif</span>
        <span class="v" style="white-space:pre-line; font-weight:400;">{{ $response->root_cause ?? 'See attached file.' }}</span>
        @foreach ($response->attachments->where('collection', Attachment::ROOT_CAUSE) as $file)
            <a href="{{ route('attachments.show', $file) }}" target="_blank" style="font-size:11.5px;">📄 {{ $file->original_name }} <span style="color:var(--text3);">· {{ $file->humanSize() }}</span></a>
        @endforeach
    </div>

    <div class="kv">
        <span class="k">Corrective actions</span>
        <div class="table-scroll" style="margin-top:4px;">
            <table>
                <thead><tr><th>Action</th><th>Responsible</th><th>Start</th><th>End</th></tr></thead>
                <tbody>
                    @forelse ($response->correctiveActions as $action)
                        <tr>
                            <td style="white-space:pre-line;">{{ $action->description }}</td>
                            <td>{{ $action->responsible ?? '—' }}</td>
                            <td class="tnum">{{ $action->starts_on?->format('M j, Y') ?? '—' }}</td>
                            <td class="tnum">{{ $action->ends_on?->format('M j, Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-row">No corrective actions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @foreach ($response->attachments->where('collection', Attachment::CORRECTIVE_ACTION) as $file)
            <a href="{{ route('attachments.show', $file) }}" target="_blank" style="font-size:11.5px;">📄 {{ $file->original_name }} <span style="color:var(--text3);">· {{ $file->humanSize() }}</span></a>
        @endforeach
    </div>

    <div style="font-size:10.5px; color:var(--text3);">
        @if ($response->submitted_at)
            Submitted by {{ $response->preparer?->name ?? 'unknown' }} on {{ $response->submitted_at->format('M j, Y g:i A') }}.
        @else
            Draft — not submitted yet.
        @endif
    </div>
</div>
