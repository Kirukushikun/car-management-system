<?php

namespace App\Livewire\Cars;

use App\Livewire\Concerns\FlexiblePagination;
use App\Models\Car;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * CAR list: "My Queue" / "My Approvals" (view=mine), "All CARs" and "Overdue" (view=overdue),
 * paginated with a rows-per-page choice.
 */
class Index extends Component
{
    use FlexiblePagination;

    #[Url]
    public string $view = '';

    #[Url]
    public int $perPage = 25;

    public function mount(): void
    {
        $this->authorize('viewAny', Car::class);

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
            'cars' => Car::query()
                ->with(['farm', 'issuedToUnits', 'subcategories.category'])
                ->forView($this->view, $user)
                ->orderByDesc('issued_on')
                ->orderByDesc('id')
                ->paginate($this->rowsPerPage()),
        ])->title($title);
    }

    private function title(): string
    {
        $item = collect(auth()->user()->role->navigation())
            ->first(fn (array $item): bool => $item['route'] === 'cars.index' && ($item['params']['view'] ?? '') === $this->view);

        return $item['label'] ?? 'All CARs';
    }
}
