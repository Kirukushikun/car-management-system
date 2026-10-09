<?php

namespace App\Livewire\Cars;

use App\Enums\CarAction;
use App\Livewire\Concerns\GuardsSubmissions;
use App\Models\Attachment;
use App\Models\Car;
use App\Models\CarResponse;
use App\Services\CarWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Phase II form (Steps 7–9) shown on the CAR page to whoever may submit the response:
 * interim containment, root cause (typed and/or attached) and corrective-action lines.
 * "Save draft" keeps partial work; "Submit for approval" requires everything and moves the CAR on.
 */
class ResponseForm extends Component
{
    use GuardsSubmissions, WithFileUploads;

    #[Locked]
    public Car $car;

    public string $containmentActions = '';

    public ?string $containmentStartsOn = null;

    public ?string $containmentEndsOn = null;

    public string $containmentResponsible = '';

    public string $rootCause = '';

    public string $rootCauseResponsible = '';

    /**
     * @var list<array{description: string, responsible: string, starts_on: ?string, ends_on: ?string}>
     */
    public array $correctiveActions = [];

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $rootCauseFiles = [];

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $correctiveActionFiles = [];

    public ?string $notice = null;

    public function mount(Car $car): void
    {
        $this->authorize('act', [$car, CarAction::SubmitResponse]);

        $source = $car->currentResponse() ?? $this->previousResponse();
        $me = auth()->user()->name;

        $this->containmentActions = $source->containment_actions ?? '';
        $this->containmentStartsOn = $source?->containment_starts_on?->toDateString() ?? today()->toDateString();
        $this->containmentEndsOn = $source?->containment_ends_on?->toDateString();
        $this->containmentResponsible = $source->containment_responsible ?? $me;
        $this->rootCause = $source->root_cause ?? '';
        $this->rootCauseResponsible = $source->root_cause_responsible ?? $me;
        $this->correctiveActions = $source?->correctiveActions->map(fn ($action): array => [
            'description' => $action->description,
            'responsible' => $action->responsible,
            'starts_on' => $action->starts_on->toDateString(),
            'ends_on' => $action->ends_on->toDateString(),
        ])->all() ?? [];

        if ($this->correctiveActions === []) {
            $this->addAction();
        }
    }

    /**
     * Step 9: a corrective action starts when containment ends — fill that in on blank lines.
     */
    public function updatedContainmentEndsOn(): void
    {
        foreach ($this->correctiveActions as $index => $action) {
            if (blank($action['starts_on'])) {
                $this->correctiveActions[$index]['starts_on'] = $this->containmentEndsOn;
            }
        }
    }

    public function addAction(): void
    {
        $this->correctiveActions[] = [
            'description' => '',
            'responsible' => auth()->user()->name,
            'starts_on' => $this->containmentEndsOn,
            'ends_on' => $this->car->implementationDeadline()->toDateString(),
        ];
    }

    public function removeAction(int $index): void
    {
        unset($this->correctiveActions[$index]);
        $this->correctiveActions = array_values($this->correctiveActions);
    }

    public function saveDraft(): void
    {
        $this->authorize('act', [$this->car, CarAction::SubmitResponse]);
        $this->validate($this->rules(complete: false));

        if ($this->guardSubmission(fn (): CarResponse => $this->persist()) === null) {
            return;
        }

        $this->clearSavedFiles();
        $this->notice = 'Draft saved. Submit it for approval when it is complete.';
    }

    public function submit(CarWorkflow $workflow): void
    {
        $this->authorize('act', [$this->car, CarAction::SubmitResponse]);
        $this->validate($this->rules(complete: true));

        $submitted = $this->guardSubmission(function () use ($workflow): bool {
            $this->persist();
            $workflow->apply($this->car, auth()->user(), CarAction::SubmitResponse);

            return true;
        });

        if ($submitted === null) {
            return;
        }

        $this->clearSavedFiles();
        session()->flash('status', "Response submitted. {$this->car->ownerLabel()} reviews it next.");
        $this->redirectRoute('cars.show', $this->car, navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(bool $complete = true): array
    {
        $required = $complete ? 'required' : 'nullable';
        $hasRootCauseFile = $this->rootCauseFiles !== []
            || $this->car->currentResponse()?->attachments()->where('collection', Attachment::ROOT_CAUSE)->exists();
        $deadline = $this->car->implementationDeadline()->toDateString();

        return [
            'containmentActions' => [$required, 'string', 'max:5000'],
            'containmentStartsOn' => [$required, 'date'],
            'containmentEndsOn' => [$required, 'date', 'after_or_equal:containmentStartsOn'],
            'containmentResponsible' => [$required, 'string', 'max:255'],
            'rootCause' => [$complete && ! $hasRootCauseFile ? 'required' : 'nullable', 'string', 'max:5000'],
            'rootCauseResponsible' => [$required, 'string', 'max:255'],
            'correctiveActions' => [$complete ? 'required' : 'nullable', 'array', $complete ? 'min:1' : 'min:0', 'max:20'],
            'correctiveActions.*.description' => [$required, 'string', 'max:2000'],
            'correctiveActions.*.responsible' => [$required, 'string', 'max:255'],
            'correctiveActions.*.starts_on' => [$required, 'date'],
            'correctiveActions.*.ends_on' => [$required, 'date', 'after_or_equal:correctiveActions.*.starts_on', 'before_or_equal:'.$deadline],
            'rootCauseFiles' => ['array', 'max:10'],
            'rootCauseFiles.*' => ['file', 'max:'.Attachment::MAX_KILOBYTES, 'extensions:'.implode(',', Attachment::ALLOWED_EXTENSIONS)],
            'correctiveActionFiles' => ['array', 'max:10'],
            'correctiveActionFiles.*' => ['file', 'max:'.Attachment::MAX_KILOBYTES, 'extensions:'.implode(',', Attachment::ALLOWED_EXTENSIONS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'rootCause.required' => 'Describe the root cause, or attach the file that explains it.',
            'containmentEndsOn.after_or_equal' => 'Containment cannot end before it starts.',
            'correctiveActions.required' => 'Add at least one corrective action.',
            'correctiveActions.min' => 'Add at least one corrective action.',
            'correctiveActions.*.ends_on.after_or_equal' => 'An action cannot end before it starts.',
            'correctiveActions.*.ends_on.before_or_equal' => 'Corrective actions must end by the implementation deadline ('.$this->car->implementationDeadline()->format('M j, Y').').',
            'rootCauseFiles.*.extensions' => 'Use photos, videos (mp4, mov), PDF or Office files.',
            'correctiveActionFiles.*.extensions' => 'Use photos, videos (mp4, mov), PDF or Office files.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'containmentActions' => 'interim containment',
            'containmentStartsOn' => 'containment start date',
            'containmentEndsOn' => 'containment end date',
            'containmentResponsible' => 'containment responsible person',
            'rootCauseResponsible' => 'root cause responsible person',
            'correctiveActions.*.description' => 'corrective action',
            'correctiveActions.*.responsible' => 'responsible person',
            'correctiveActions.*.starts_on' => 'start date',
            'correctiveActions.*.ends_on' => 'end date',
        ];
    }

    public function render(): View
    {
        return view('livewire.cars.response-form', [
            'existingFiles' => $this->car->currentResponse()?->attachments ?? collect(),
            'deadline' => $this->car->implementationDeadline(),
            'heading' => match (true) {
                $this->car->currentResponse()?->submitted_at !== null => 'Revise your response',
                $this->car->current_round > 1 => 'Propose a new solution',
                default => 'Your response',
            },
        ]);
    }

    /**
     * The new files are attached now; empty the pickers so they are not attached twice.
     */
    private function clearSavedFiles(): void
    {
        $this->rootCauseFiles = [];
        $this->correctiveActionFiles = [];
        $this->car->unsetRelation('currentRound');
    }

    /**
     * Save the form into the current round's response, replacing its corrective-action lines.
     * Callers run it through guardSubmission(), so the lines and files are saved all-or-nothing.
     */
    private function persist(): CarResponse
    {
        return Attachment::atomically(function (): CarResponse {
            $round = $this->car->currentRound()->firstOrFail();
            $response = CarResponse::firstOrNew(['car_round_id' => $round->id], ['car_id' => $this->car->id]);

            $response->fill([
                'containment_actions' => $this->containmentActions ?: null,
                'containment_starts_on' => $this->containmentStartsOn ?: null,
                'containment_ends_on' => $this->containmentEndsOn ?: null,
                'containment_responsible' => $this->containmentResponsible ?: null,
                'root_cause' => $this->rootCause ?: null,
                'root_cause_responsible' => $this->rootCauseResponsible ?: null,
                'prepared_by' => auth()->id(),
            ])->save();

            $response->correctiveActions()->delete();

            foreach (array_values(array_filter($this->correctiveActions, fn (array $action): bool => filled($action['description']))) as $index => $action) {
                $response->correctiveActions()->create([
                    'position' => $index + 1,
                    'description' => $action['description'],
                    'responsible' => $action['responsible'],
                    'starts_on' => $action['starts_on'],
                    'ends_on' => $action['ends_on'],
                ]);
            }

            foreach ($this->rootCauseFiles as $file) {
                Attachment::store($response, $file, Attachment::ROOT_CAUSE, auth()->user());
            }

            foreach ($this->correctiveActionFiles as $file) {
                Attachment::store($response, $file, Attachment::CORRECTIVE_ACTION, auth()->user());
            }

            return $response;
        });
    }

    /**
     * After "not effective" a new round starts empty; begin it from the last round's answer.
     */
    private function previousResponse(): ?CarResponse
    {
        return $this->car->responses()->with('correctiveActions')->latest('car_round_id')
            ->whereHas('round', fn ($query) => $query->where('number', '<', $this->car->current_round))
            ->first();
    }
}
