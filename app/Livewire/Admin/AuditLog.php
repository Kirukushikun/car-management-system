<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\FlexiblePagination;
use App\Models\Audit;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Who changed which user or maintained value (farm, unit, category, sub-category, complaint
 * type), and what changed. CAR changes are in each
 * CAR's own history.
 */
#[Title('Audit Log')]
class AuditLog extends Component
{
    use FlexiblePagination;

    #[Url]
    public int $perPage = 25;

    public function mount(): void
    {
        $this->authorize('administer');
    }

    public function render(): View
    {
        return view('livewire.admin.audit-log', [
            'audits' => Audit::with(['user', 'auditable'])->latest('id')->paginate($this->rowsPerPage()),
        ]);
    }
}
