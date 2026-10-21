<?php

namespace App\Support;

/**
 * The page sizes every list offers (see App\Livewire\Concerns\FlexiblePagination and the
 * table-footer component).
 */
final class Pagination
{
    /**
     * @var list<int>
     */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public const DEFAULT_PER_PAGE = 25;
}
