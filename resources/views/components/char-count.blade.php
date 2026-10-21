{{--
    Live "245 / 2,000" counter for the input or textarea in the same field. It reads the field's
    maxlength, so the limit is set once on the field itself. Amber near the limit, red at it.
--}}
<div {{ $attributes->merge(['class' => 'char-count']) }} aria-live="polite"
     x-data="{ n: 0, max: 0 }"
     x-init="
        const field = $el.closest('.field').querySelector('textarea, input:not([type=checkbox]):not([type=file]):not([type=date])');
        max = field ? Number(field.maxLength) : 0;
        n = field ? field.value.length : 0;
        field?.addEventListener('input', () => n = field.value.length);
     "
     x-show="max > 0"
     :class="{ 'is-near': n >= max * 0.9 && n < max, 'is-full': n >= max }">
    <span x-text="n.toLocaleString()"></span> / <span x-text="max.toLocaleString()"></span>
</div>
