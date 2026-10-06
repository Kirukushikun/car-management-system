<?php

namespace App\Livewire\Dashboard;

use App\Models\BusinessLine;
use App\Models\Car;
use App\Models\Farm;
use App\Services\DashboardMetrics;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Monitor dashboard: open/overdue/closed counts, response and resolution time, frequency of
 * issuance and repeat offenses — filterable by period, business line and farm.
 */
#[Title('Dashboard')]
class Index extends Component
{
    /**
     * Look-back period in days, or "all".
     */
    #[Url]
    public string $period = '90';

    #[Url]
    public ?int $line = null;

    #[Url]
    public ?int $farm = null;

    public function mount(): void
    {
        $this->authorize('view-dashboard');

        if (! array_key_exists($this->period, self::periods())) {
            $this->period = '90';
        }
    }

    /**
     * @return array<string, string>
     */
    public static function periods(): array
    {
        return ['30' => 'Last 30 days', '90' => 'Last 90 days', '180' => 'Last 6 months', '365' => 'Last 12 months', 'all' => 'All time'];
    }

    public function render(): View
    {
        $stats = (new DashboardMetrics(
            days: $this->period === 'all' ? null : (int) $this->period,
            businessLineId: $this->line,
            farmId: $this->farm,
        ))->summary();

        return view('livewire.dashboard.index', [
            'stats' => $stats,
            'maxMonth' => max([1, ...array_values($stats['months'])]),
            'maxOffense' => max([1, ...array_column($stats['repeat_offenses'], 'count')]),
            'maxUnit' => max([1, ...array_column($stats['by_unit'], 'count')]),
            'periods' => self::periods(),
            'lines' => BusinessLine::orderBy('id')->get(),
            'farms' => Farm::orderBy('id')->get(),
            'attention' => Car::overdue()
                ->when($this->line, fn ($query) => $query->whereRelation('category', 'business_line_id', $this->line))
                ->when($this->farm, fn ($query) => $query->where('farm_id', $this->farm))
                ->with(['farm', 'issuedToUnit', 'category', 'subcategory'])
                ->orderBy('issued_on')
                ->get(),
        ]);
    }
}
