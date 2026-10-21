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
            <x-tip :text="$tip" label="About this field" />
        @endif
    </span>
</div>
