<?php

namespace App\Livewire\Admin;

use App\Services\ScaffoldData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Category matrix — response and CA implementation timelines per category. Read-only until Phase 1.
 */
#[Title('Category Matrix')]
class Matrix extends Component
{
    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('administer');
    }

    public function save(): void
    {
        $this->authorize('administer');

        $this->notice = 'Saving the matrix is a stub in the UI scaffold — it is wired up in Phase 1. Nothing was changed.';
    }

    public function render(): View
    {
        return view('livewire.admin.matrix', [
            'matrix' => ScaffoldData::matrix(),
        ]);
    }
}
