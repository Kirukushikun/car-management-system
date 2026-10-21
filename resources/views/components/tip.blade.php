@props(['text', 'label' => 'More information'])

{{-- An info icon that shows a short tip on hover, focus or tap. Used at the right of form labels (field-label) or on its own next to a control. --}}
<span {{ $attributes->merge(['class' => 'field-tip']) }} x-data="{ open: false }" x-on:mouseenter="open = true" x-on:mouseleave="open = false" x-on:click.outside="open = false">
    <button type="button" class="field-tip-button" x-on:click.prevent="open = ! open" x-on:focus="open = true" x-on:blur="open = false"
            :aria-expanded="open" aria-label="{{ $label }}"><x-icon name="info" /></button>
    <span class="field-tip-bubble" role="tooltip" x-show="open" x-cloak x-transition.opacity>{{ $text }}</span>
</span>
