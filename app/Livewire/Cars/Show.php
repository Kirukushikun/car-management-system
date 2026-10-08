<?php

namespace App\Livewire\Cars;

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Models\Attachment;
use App\Models\Car;
use App\Models\CarEvent;
use App\Models\CarRound;
use App\Services\CarWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * CAR detail: the three phase cards, the role-gated action bar and the history timeline.
 * Actions that need more than a click have their own forms on the page — the response
 * (ResponseForm) and the implementation evidence (EvidenceForm); everything else runs here.
 */
class Show extends Component
{
    /**
     * Actions completed through a form on the page rather than a single button.
     *
     * @var array<string, string>
     */
    private const FORM_ACTIONS = [
        'submit_response' => 'Fill in the response form below, then use “Submit for approval”.',
        'upload_evidence' => 'Attach the evidence in the form below, then submit it for the effectiveness check.',
    ];

    /**
     * History entries shown before the rest fold behind "Show N more entries".
     */
    private const HISTORY_VISIBLE = 4;

    public Car $car;

    public string $note = '';

    public string $newDueOn = '';

    public ?string $notice = null;

    public function mount(Car $car): void
    {
        $this->authorize('view', $car);

        $this->newDueOn = CarbonImmutable::today()->addDays(7)->toDateString();
    }

    public function act(string $action, CarWorkflow $workflow): void
    {
        $action = CarAction::from($action);
        $this->authorize('act', [$this->car, $action]);

        if (isset(self::FORM_ACTIONS[$action->value])) {
            $this->notice = self::FORM_ACTIONS[$action->value];

            return;
        }

        $workflow->apply(
            $this->car,
            auth()->user(),
            $action,
            note: $action->requiresNote() ? $this->note : null,
            newDueOn: $action->requiresNewDueDate() && $this->newDueOn !== '' ? CarbonImmutable::parse($this->newDueOn) : null,
        );

        $this->note = '';
        $this->resetErrorBag();
        $this->notice = "{$action->pastTense()}.";
    }

    public function render(CarWorkflow $workflow): View
    {
        $this->car->load([
            'farm', 'issuedToUnit.businessLine', 'category', 'subcategory', 'requestor', 'attachments', 'events.actor',
            'responses' => fn ($query) => $query->whereNotNull('submitted_at')->with(['round', 'correctiveActions', 'attachments', 'preparer']),
            'rounds' => fn ($query) => $query->with(['attachments' => fn ($query) => $query->where('collection', Attachment::IMPLEMENTATION_EVIDENCE), 'evidenceUploader']),
        ]);

        $actions = $workflow->availableActions($this->car, auth()->user());

        return view('livewire.cars.show', [
            'actions' => $actions,
            'needsNote' => collect($actions)->contains(fn (CarAction $action): bool => $action->requiresNote()),
            'needsNewDueDate' => in_array(CarAction::NotAccept, $actions, true),
            'actionNote' => $this->actionNote($actions),
            'canRespond' => in_array(CarAction::SubmitResponse, $actions, true),
            'canUploadEvidence' => in_array(CarAction::UploadEvidence, $actions, true),
            'submittedResponses' => $this->car->responses->sortByDesc(fn ($response) => $response->round->number)->values(),
            'verificationRounds' => $this->verificationRounds(),
            'isOverdue' => $this->car->isOverdue(),
            'historyVisible' => self::HISTORY_VISIBLE,
        ])->title($this->car->reference);
    }

    /**
     * Phase III per round, like Part VI of the paper form: the evidence, the effectiveness check
     * and the final acceptance decision. Newest round first; rounds with nothing yet are skipped.
     *
     * @return list<array{round: CarRound, evidence: Collection<int, Attachment>, verification: ?CarEvent, acceptance: ?CarEvent}>
     */
    private function verificationRounds(): array
    {
        $events = $this->car->events->groupBy('round');

        return $this->car->rounds
            ->sortByDesc('number')
            ->map(fn ($round): array => [
                'round' => $round,
                'evidence' => $round->attachments,
                'verification' => ($events[$round->number] ?? collect())->last(fn ($event) => in_array($event->action, [CarAction::MarkEffective, CarAction::MarkNotEffective], true)),
                'acceptance' => ($events[$round->number] ?? collect())->last(fn ($event) => in_array($event->action, [CarAction::Accept, CarAction::NotAccept], true)),
            ])
            ->filter(fn (array $row): bool => $row['round']->evidence_uploaded_at !== null || $row['verification'] || $row['acceptance'])
            ->values()
            ->all();
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
                ? 'The response was returned or the action was not effective — see the reason in the history, update the response below and submit it again.'
                : 'Record the interim containment, root cause and corrective actions below, then submit them for approval.',
            in_array(CarAction::ApproveResponse, $actions, true) => 'Review the root cause and corrective actions in Phase II. Approve to move to implementation, or return them for revision with a reason.',
            in_array(CarAction::UploadEvidence, $actions, true) => $this->car->status === CarStatus::OpenNotAccepted
                ? "Not accepted by the Requestor Approver — re-implement and upload new evidence by {$this->car->implementationDeadline()->format('M j, Y')}."
                : 'Upload files and photos proving the corrective actions were carried out.',
            in_array(CarAction::MarkEffective, $actions, true) => 'Review the implementation evidence in Phase III. Was the corrective action effective?',
            in_array(CarAction::Accept, $actions, true) => 'The corrective action was validated as effective. Accept to close this CAR, or send it back with a new end date.',
            default => 'This CAR is waiting on you.',
        };
    }
}
