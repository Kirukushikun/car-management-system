<?php

namespace App\Livewire\Concerns;

use App\Support\Pagination;
use Livewire\WithPagination;

/**
 * Pagination with a "rows per page" choice, shared by every list. The component declares its own
 * `#[Url] public int $perPage` (so each list keeps its own default and it survives in the link),
 * paginates with rowsPerPage(), and renders <x-table-footer :paginator="..." /> under the table.
 */
trait FlexiblePagination
{
    use WithPagination;

    /**
     * A new page size starts from the first page; anything not offered falls back to 25.
     */
    public function updatedPerPage(): void
    {
        $this->perPage = $this->rowsPerPage();

        foreach (array_keys($this->paginators) ?: ['page'] as $pageName) {
            $this->resetPage($pageName);
        }
    }

    protected function rowsPerPage(): int
    {
        return in_array((int) $this->perPage, Pagination::PER_PAGE_OPTIONS, true) ? (int) $this->perPage : Pagination::DEFAULT_PER_PAGE;
    }
}
