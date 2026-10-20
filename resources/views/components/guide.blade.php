@props(['name', 'title', 'subtitle' => null, 'currentPhase' => null])

{{--
    A side panel with the guide for one module or form, opened by the guide-button component with
    the same name (or by dispatching open-guide with that name). Each module puts its own content
    in the slot — keep it short: an overview tab, plus a reference tab where one helps.
    Field-level help belongs on the field itself (the field-label component's tip), not here.
--}}
<div x-data="{ open: false }"
     x-on:open-guide.window="open = $event.detail.name === @js($name); if (open) { $nextTick(() => $refs.close.focus()) }"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak class="guide-backdrop" x-transition.opacity
     x-on:click.self="open = false">
    <aside class="guide-panel" role="dialog" aria-modal="true" aria-labelledby="guide-{{ $name }}-title">
        <div class="guide-head">
            <div>
                <div class="guide-title" id="guide-{{ $name }}-title">{{ $title }}</div>
                @if ($subtitle)
                    <div class="guide-subtitle">{{ $subtitle }}</div>
                @endif
            </div>
            <button type="button" class="btn btn-ghost guide-close" x-ref="close" x-on:click="open = false" aria-label="Close the guide"><x-icon name="close" /></button>
        </div>

        @if ($currentPhase)
            {{-- On a CAR: switch between the phase guides; the CAR's current phase is marked "now". --}}
            <div class="guide-phases" role="tablist" aria-label="Phase">
                @foreach (['phase-one' => 'Phase I', 'phase-two' => 'Phase II', 'phase-three' => 'Phase III'] as $phase => $label)
                    <button type="button" role="tab" @class(['guide-phase', 'is-active' => $phase === $name]) aria-selected="{{ $phase === $name ? 'true' : 'false' }}"
                            x-on:click="$dispatch('open-guide', { name: @js($phase) })">
                        {{ $label }}
                        @if ($phase === $currentPhase)
                            <span class="guide-phase-now">now</span>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif

        <div class="guide-body">
            {{ $slot }}
        </div>
    </aside>
</div>
