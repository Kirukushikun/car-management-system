@props(['title', 'subtitle' => null])

<div class="topbar">
    <div>
        <h2>{{ $title }}</h2>
        @if ($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
