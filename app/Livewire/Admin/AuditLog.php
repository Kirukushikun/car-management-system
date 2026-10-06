<?php

namespace App\Livewire\Admin;

use App\Models\Audit;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Who changed which user, category or sub-category, and what changed. CAR changes are in each
 * CAR's own history.
 */
#[Title('Audit Log')]
class AuditLog extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('administer');
    }

    public function render(): View
    {
        return view('livewire.admin.audit-log', [
            'audits' => Audit::with(['user', 'auditable'])->latest('id')->paginate(30),
        ]);
    }
}
