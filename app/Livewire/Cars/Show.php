<?php

namespace App\Livewire\Cars;

use App\Services\ScaffoldData;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * CAR detail: the three phase cards, the role-gated action bar and the history timeline.
 * Action buttons are stubs until the CarWorkflow service exists (Phases 2–5).
 */
class Show extends Component
{
    public string $reference;

    public ?string $notice = null;

    public string $newEndDate = '';

    public function mount(string $reference): void
    {
        abort_if(ScaffoldData::find($reference) === null, 404);

        $this->reference = $reference;
        $this->newEndDate = ScaffoldData::today()->addDays(7)->toDateString();
    }

    /**
     * Stub handler: confirms the button is one the current user may press, then explains when it gets wired up.
     */
    public function runAction(string $label): void
    {
        $actions = ScaffoldData::actionsFor($this->car(), auth()->user());
        $button = collect($actions['buttons'] ?? [])->firstWhere('label', $label);

        abort_if($button === null, 403);

        $this->notice = "“{$label}” is a stub in the UI scaffold — it is wired up in Phase {$button['phase']} of the development plan. Nothing was changed.";
    }

    public function render(): View
    {
        $car = $this->car();

        return view('livewire.cars.show', [
            'car' => $car,
            'deadlines' => ScaffoldData::deadlines($car),
            'phase' => ScaffoldData::phase($car['status']),
            'owner' => ScaffoldData::owner($car),
            'isOverdue' => ScaffoldData::isOverdue($car),
            'actions' => ScaffoldData::actionsFor($car, auth()->user()),
        ])->title($car['ref']);
    }

    /**
     * @return array<string, mixed>
     */
    private function car(): array
    {
        return ScaffoldData::find($this->reference);
    }
}
