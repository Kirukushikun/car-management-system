<?php

namespace App\Livewire\Cars;

use App\Services\ScaffoldData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * CAR list: "My Queue" / "My Approvals" (view=mine), "All CARs" and "Overdue" (view=overdue).
 */
class Index extends Component
{
    #[Url]
    public string $view = '';

    public function mount(): void
    {
        match ($this->view) {
            'mine' => $this->authorize('view-queue'),
            'overdue' => $this->authorize('view-overdue'),
            default => $this->view = '',
        };
    }

    public function render(): View
    {
        $user = auth()->user();
        $title = $this->title();

        return view('livewire.cars.index', [
            'heading' => $title,
            'subtitle' => match ($this->view) {
                'mine' => "Items waiting on you — {$user->roleWithScope()}.",
                'overdue' => 'CARs past their response or implementation deadline.',
                default => 'All CARs across TABLE EGG and DOP operations.',
            },
            'cars' => ScaffoldData::carsFor($this->view ?: 'all', $user),
        ])->title($title);
    }

    private function title(): string
    {
        $item = collect(auth()->user()->role->navigation())
            ->first(fn (array $item): bool => $item['route'] === 'cars.index' && ($item['params']['view'] ?? '') === $this->view);

        return $item['label'] ?? 'All CARs';
    }
}
