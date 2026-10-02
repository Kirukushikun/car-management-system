<?php

namespace App\Livewire\Dashboard;

use App\Services\ScaffoldData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Monitor dashboard. Figures are the mockup's sample values until DashboardMetrics lands in Phase 7.
 */
#[Title('Dashboard')]
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('view-dashboard');
    }

    public function render(): View
    {
        $stats = ScaffoldData::dashboard();

        return view('livewire.dashboard.index', [
            'stats' => $stats,
            'maxMonth' => max($stats['months']),
            'maxOffender' => max(array_column($stats['repeat_offenders'], 'count')),
            'attention' => ScaffoldData::carsFor('overdue', auth()->user()),
        ]);
    }
}
