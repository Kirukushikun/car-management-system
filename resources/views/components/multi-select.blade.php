@props(['model', 'options', 'placeholder' => 'Select…'])

{{--
    A dropdown that picks several values, bound to a Livewire array property.
    $options: list of ['value' => int|string, 'label' => string, 'group' => ?string] — consecutive
    options with the same group are listed under that heading.
--}}
@php($labels = collect($options)->mapWithKeys(fn (array $option): array => [(string) $option['value'] => $option['label']]))

<div {{ $attributes->merge(['class' => 'multi-select']) }}
     x-data="{
        open: false,
        selected: $wire.entangle(@js($model)).live,
        labels: @js($labels),
        has(value) { return (this.selected ?? []).map(String).includes(String(value)); },
        toggle(value) {
            this.selected = this.has(value)
                ? this.selected.filter(item => String(item) !== String(value))
                : [...(this.selected ?? []), value];
        },
     }"
     x-on:click.outside="open = false"
     x-on:keydown.escape="open = false">
    <div class="multi-select-control" role="combobox" tabindex="0" aria-haspopup="listbox"
         :aria-expanded="open" x-on:click="open = ! open"
         x-on:keydown.enter.prevent="open = ! open" x-on:keydown.space.prevent="open = ! open">
        <span class="multi-select-placeholder" x-show="! (selected ?? []).length">{{ $placeholder }}</span>
        <template x-for="value in (selected ?? [])" :key="String(value)">
            <span class="multi-select-chip">
                <span x-text="labels[String(value)]"></span>
                <button type="button" class="multi-select-remove" x-on:click.stop="toggle(value)" :aria-label="'Remove ' + labels[String(value)]"><x-icon name="close" /></button>
            </span>
        </template>
        <span class="multi-select-caret" aria-hidden="true"><x-icon name="chevron-down" /></span>
    </div>

    <div class="multi-select-panel" role="listbox" aria-multiselectable="true" x-show="open" x-cloak x-transition.opacity>
        @foreach ($options as $option)
            @if (($option['group'] ?? null) !== null && ($loop->first || ($options[$loop->index - 1]['group'] ?? null) !== $option['group']))
                <div class="multi-select-group">{{ $option['group'] }}</div>
            @endif
            <label class="multi-select-option" wire:key="{{ $model }}-option-{{ $option['value'] }}">
                <input type="checkbox" value="{{ $option['value'] }}" :checked="has(@js($option['value']))" x-on:change="toggle(@js($option['value']))">
                {{ $option['label'] }}
            </label>
        @endforeach
    </div>
</div>
