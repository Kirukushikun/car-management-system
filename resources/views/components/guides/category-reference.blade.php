@props(['lines', 'current' => null])

{{--
    The category reference: per business line, each category with its timeline, collapsed —
    open one to see its sub-categories and what to report under each. Searching opens the
    categories that match. Starts on the business line picked on the form each time the guide opens.
--}}
<div x-data="{ line: @js($current ?? $lines->first()?->name), search: '' }"
     data-current-line="{{ $current ?? $lines->first()?->name }}"
     x-on:open-guide.window="line = $el.dataset.currentLine || line">
    <div class="guide-tools">
        <div class="guide-tabs" role="tablist" aria-label="Business line">
            @foreach ($lines as $line)
                <button type="button" role="tab" class="guide-tab guide-tab-small" :class="line === @js($line->name) && 'is-active'"
                        :aria-selected="line === @js($line->name)" x-on:click="line = @js($line->name)">{{ $line->name }}</button>
            @endforeach
        </div>
        <input type="search" x-model="search" class="guide-search" placeholder="Search sub-categories…" aria-label="Search categories and sub-categories">
    </div>

    @foreach ($lines as $line)
        <div x-show="line === @js($line->name)">
            @foreach ($line->categories as $category)
                <details class="guide-category" wire:key="guide-category-{{ $category->id }}"
                         x-data="{ matches() { return [...$el.querySelectorAll('[data-search]')].some(item => item.dataset.search.includes(search.toLowerCase())) } }"
                         x-show="! search || matches()"
                         x-effect="if (search) { $el.open = matches() }">
                    <summary class="guide-category-head">
                        <x-icon name="chevron-right" class="guide-chevron" />
                        <strong>{{ $category->name }}</strong>
                        <span class="guide-days">{{ $category->response_days }}d to respond · {{ $category->implementation_days }}d to implement</span>
                    </summary>
                    <dl class="guide-list">
                        @foreach ($category->subcategories as $subcategory)
                            <div class="guide-item" data-search="{{ str($subcategory->name.' '.$subcategory->description)->lower() }}"
                                 x-show="! search || $el.dataset.search.includes(search.toLowerCase())">
                                <dt>{{ $subcategory->name }}</dt>
                                <dd>{{ $subcategory->description ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </details>
            @endforeach
        </div>
    @endforeach
</div>
