<?php

namespace App\Livewire\Cars;

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Models\Car;
use App\Services\CarWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * CAR detail: the three phase cards, the role-gated action bar and the history timeline.
 * Phase I and II are live: release / reject / resubmit, the response form (ResponseForm) and its
 * approval or return, plus voiding. Phase III buttons stay stubs until Phase 5.
 */
class Show extends Component
{
    /**
     * Actions that run for real in this phase of the build.
     *
     * @var list<CarAction>
     */
    private const LIVE_ACTIONS = [
        CarAction::Release, CarAction::Reject, CarAction::Void,
        CarAction::ApproveResponse, CarAction::ReturnResponse,
    ];

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

        if ($action === CarAction::SubmitResponse) {
            $this->notice = 'Fill in the response form below, then use “Submit for approval”.';

            return;
        }

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
        $this->car->load([
            'farm', 'issuedToUnit.businessLine', 'category', 'subcategory', 'requestor', 'attachments', 'events.actor',
            'responses' => fn ($query) => $query->whereNotNull('submitted_at')->with(['round', 'correctiveActions', 'attachments', 'preparer']),
        ]);

        $actions = $workflow->availableActions($this->car, auth()->user());

        return view('livewire.cars.show', [
            'actions' => $actions,
            'needsNote' => collect($actions)->contains(fn (CarAction $action): bool => $action->requiresNote()),
            'actionNote' => $this->actionNote($actions),
            'canRespond' => in_array(CarAction::SubmitResponse, $actions, true),
            'submittedResponses' => $this->car->responses->sortByDesc(fn ($response) => $response->round->number)->values(),
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
            in_array(CarAction::SubmitResponse, $actions, true) => $this->car->status === CarStatus::ReturnedToResponder
                ? 'Your response was returned for revision — see the reason in the history, update the response below and submit it again.'
                : 'Record the interim containment, root cause and corrective actions below, then submit them for approval.',
            in_array(CarAction::ApproveResponse, $actions, true) => 'Review the root cause and corrective actions in Phase II. Approve to move to implementation, or return them for revision with a reason.',
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
