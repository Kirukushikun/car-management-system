@props(['name', 'label' => 'Guide'])

<button type="button" {{ $attributes->merge(['class' => 'btn btn-secondary guide-button']) }}
        x-data x-on:click="$dispatch('open-guide', { name: @js($name) })">
    <x-icon name="info" /> {{ $label }}
</button>
