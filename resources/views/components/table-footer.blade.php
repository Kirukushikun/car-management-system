@props(['paginator', 'label' => 'CARs'])

{{--
    The footer under every list: "Showing 26–50 of 176 CARs", a rows-per-page choice (bound to the
    component's $perPage, see App\Livewire\Concerns\FlexiblePagination) and the page buttons.
--}}
@if ($paginator->total() > 0)
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $pageName = $paginator->getPageName();
        $pages = collect([1, $last, ...range(max(1, $current - 1), min($last, $current + 1))])->unique()->sort()->values();
    @endphp
    <div {{ $attributes->merge(['class' => 'table-footer']) }}>
        <div class="table-footer-summary">
            Showing <strong class="tnum">{{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}</strong>
            of <strong class="tnum">{{ number_format($paginator->total()) }}</strong> {{ $label }}
        </div>

        <label class="table-footer-size">
            Rows per page
            <select wire:model.live="perPage" aria-label="Rows per page">
                @foreach (\App\Support\Pagination::PER_PAGE_OPTIONS as $size)
                    <option value="{{ $size }}">{{ $size }}</option>
                @endforeach
            </select>
        </label>

        @if ($last > 1)
            <nav class="table-pages" aria-label="Pages">
                <button type="button" class="table-page" wire:click="gotoPage(1, '{{ $pageName }}')" @disabled($current === 1) aria-label="First page"><x-icon name="first" /></button>
                <button type="button" class="table-page" wire:click="previousPage('{{ $pageName }}')" @disabled($current === 1) aria-label="Previous page"><x-icon name="previous" /></button>
                @foreach ($pages as $page)
                    @if (! $loop->first && $page - $pages[$loop->index - 1] > 1)
                        <span class="table-page-gap" aria-hidden="true">…</span>
                    @endif
                    <button type="button" @class(['table-page', 'is-current' => $page === $current]) wire:click="gotoPage({{ $page }}, '{{ $pageName }}')"
                            @if ($page === $current) aria-current="page" @endif aria-label="Page {{ $page }}">{{ $page }}</button>
                @endforeach
                <button type="button" class="table-page" wire:click="nextPage('{{ $pageName }}')" @disabled($current === $last) aria-label="Next page"><x-icon name="next" /></button>
                <button type="button" class="table-page" wire:click="gotoPage({{ $last }}, '{{ $pageName }}')" @disabled($current === $last) aria-label="Last page"><x-icon name="last" /></button>
            </nav>
        @endif
    </div>
@endif
