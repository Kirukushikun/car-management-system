@props(['title', 'subtitle' => null])

{{-- Page title row. Tags that belong with the title (e.g. a CAR's status) go in the badges slot; actions go in the default slot, on the right. --}}
<div class="topbar">
    <div>
        <div class="topbar-title">
            <h2>{{ $title }}</h2>
            {{ $badges ?? '' }}
        </div>
        @if ($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
