<?php

namespace App\Livewire\Dashboard;

use App\Enums\CarStatus;
use App\Models\Car;
use App\Services\ScaffoldData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Monitor dashboard. Open / Phase I / overdue counts are live; the time averages, monthly chart and
 * repeat offenders are the mockup's sample values until DashboardMetrics lands in Phase 7.
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
        $stats = [
            ...ScaffoldData::dashboard(),
            'open' => Car::open()->count(),
            'phase_one' => Car::whereIn('status', array_filter(CarStatus::cases(), fn (CarStatus $status): bool => $status->phase() === 1))->count(),
            'overdue' => Car::overdue()->count(),
        ];

        return view('livewire.dashboard.index', [
            'stats' => $stats,
            'maxMonth' => max($stats['months']),
            'maxOffender' => max(array_column($stats['repeat_offenders'], 'count')),
            'attention' => Car::overdue()->with(['farm', 'issuedToUnit', 'category', 'subcategory'])->orderBy('issued_on')->get(),
        ]);
    }
}
