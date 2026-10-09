@props(['number', 'outcome' => null])

@use('App\Enums\CarAction')

{{-- "Solution N" header for one round of a CAR, with why it ended when it was not effective or not accepted. --}}
<div {{ $attributes->merge(['class' => 'solution-badge'.($outcome ? ' is-ended' : ' is-current')]) }}>
    <span class="solution-num">Solution {{ $number }}</span>
    @if ($outcome)
        <x-pill tone="red">{{ $outcome->action === CarAction::NotAccept ? 'Not accepted' : 'Not effective' }}</x-pill>
        <span class="solution-meta">{{ $outcome->actor?->name }} · {{ $outcome->created_at->format('M j, Y') }}</span>
        @if ($outcome->note)
            <span class="solution-reason">“{{ $outcome->note }}”</span>
        @endif
    @endif
</div>
