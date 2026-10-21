<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * A maintained value (farm, unit, category, sub-category, complaint type) the IT Admin can switch
 * off. Inactive values are no longer offered on new CARs; CARs that already carry them keep them.
 */
trait CanBeDeactivated
{
    public function initializeCanBeDeactivated(): void
    {
        $this->mergeCasts(['is_active' => 'boolean']);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * Values a form may offer: the active ones, plus any already picked (so a CAR being corrected
     * keeps a value that was deactivated since).
     *
     * @param  Builder<static>  $query
     * @param  array<int, int|string|null>  $keptIds
     */
    public function scopeOfferable(Builder $query, array $keptIds = []): void
    {
        $keptIds = array_values(array_filter(array_map('intval', $keptIds)));

        $query->where(fn (Builder $query) => $query->where($this->qualifyColumn('is_active'), true)
            ->when($keptIds !== [], fn (Builder $query) => $query->orWhereIn($this->qualifyColumn('id'), $keptIds)));
    }
}
