<?php

namespace App\Livewire\Cars;

use App\Enums\CarAction;
use App\Models\Car;
use App\Services\CarWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * CAR detail: the three phase cards, the role-gated action bar and the history timeline.
 * Phase I actions (release, reject, resubmit) and voiding are live; the Phase II/III buttons are
 * stubs until their forms land in Phases 4–5.
 */
class Show extends Component
{
    /**
     * Actions that run for real in this phase of the build.
     *
     * @var list<CarAction>
     */
    private const LIVE_ACTIONS = [CarAction::Release, CarAction::Reject, CarAction::Void];

    public Car $car;

    public string $note = '';

    public ?string $notice = null;

    public function mount(Car $car): void
    {
        $this->authorize('view', $car);
    }

    public function act(string $action, CarWorkflow $workflow): void
    {
        $action = CarAction::from($action);
        $this->authorize('act', [$this->car, $action]);

        if (! in_array($action, self::LIVE_ACTIONS, true)) {
            $this->notice = "“{$action->label()}” is wired up in Phase {$this->buildPhaseFor($action)} of the development plan. Nothing was changed.";

            return;
        }

        $workflow->apply($this->car, auth()->user(), $action, note: $action->requiresNote() ? $this->note : null);

        $this->note = '';
        $this->resetErrorBag();
        $this->notice = "{$action->pastTense()}.";
    }

    public function render(CarWorkflow $workflow): View
    {
        $this->car->load(['farm', 'issuedToUnit.businessLine', 'category', 'subcategory', 'requestor', 'attachments', 'events.actor']);

        $actions = $workflow->availableActions($this->car, auth()->user());

        return view('livewire.cars.show', [
            'actions' => $actions,
            'needsNote' => collect($actions)->contains(fn (CarAction $action): bool => $action->requiresNote()),
            'actionNote' => $this->actionNote($actions),
            'isOverdue' => $this->car->isOverdue(),
        ])->title($this->car->reference);
    }

    /**
     * One line telling the user what is expected of them on this CAR.
     *
     * @param  list<CarAction>  $actions
     */
    private function actionNote(array $actions): string
    {
        return match (true) {
            $actions === [CarAction::Void] => 'As IT Admin you can void this CAR — for duplicates or CARs filed in error. A reason is required and kept in the history.',
            in_array(CarAction::Release, $actions, true) => 'Investigation done? Release this CAR to the Responder, or reject it back to the Requestor for clarity.',
            in_array(CarAction::Resubmit, $actions, true) => 'The Requestor Approver sent this back. Correct the details, then resubmit it for release.',
            default => 'This CAR is waiting on you.',
        };
    }

    /**
     * Which build phase turns a stubbed action into a real one.
     */
    private function buildPhaseFor(CarAction $action): int
    {
        return match ($action) {
            CarAction::SubmitResponse, CarAction::ApproveResponse, CarAction::ReturnResponse => 4,
            default => 5,
        };
    }
}
