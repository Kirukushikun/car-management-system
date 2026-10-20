@props(['for' => null, 'tip' => null])

{{--
    A form label with an optional info icon at its far right. Hover, focus or tap it to see a short tip
    about the field. Extra controls for the label row (e.g. "Remove pair") go in the actions slot.
--}}
<div class="field-label">
    <label @if ($for) for="{{ $for }}" @endif>{{ $slot }}</label>
    <span class="field-label-end">
        {{ $actions ?? '' }}
        @if ($tip)
            <span class="field-tip" x-data="{ open: false }" x-on:mouseenter="open = true" x-on:mouseleave="open = false" x-on:click.outside="open = false">
                <button type="button" class="field-tip-button" x-on:click="open = ! open" x-on:focus="open = true" x-on:blur="open = false"
                        :aria-expanded="open" aria-label="About this field"><x-icon name="info" /></button>
                <span class="field-tip-bubble" role="tooltip" x-show="open" x-cloak x-transition.opacity>{{ $tip }}</span>
            </span>
        @endif
    </span>
</div>
