<?php

namespace App\Livewire\Cars;

use App\Enums\CarAction;
use App\Models\Attachment;
use App\Models\Car;
use App\Services\CarWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Step 11: the Responder uploads files and photos proving the corrective actions were carried
 * out, with the responsible person and optional notes. Submitting sends the CAR to the
 * Responder Approver's effectiveness check (Step 12).
 */
class EvidenceForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public Car $car;

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $files = [];

    public string $responsible = '';

    public string $notes = '';

    public function mount(Car $car): void
    {
        $this->authorize('act', [$car, CarAction::UploadEvidence]);

        $round = $car->currentRound()->firstOrFail();
        $this->responsible = $round->evidence_responsible ?? auth()->user()->name;
        $this->notes = $round->evidence_notes ?? '';
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function submit(CarWorkflow $workflow): void
    {
        $this->authorize('act', [$this->car, CarAction::UploadEvidence]);

        $round = $this->car->currentRound()->firstOrFail();
        $this->validate([
            'files' => [$round->hasEvidence() ? 'nullable' : 'required', 'array', 'max:10'],
            'files.*' => ['file', 'max:'.Attachment::MAX_KILOBYTES, 'extensions:'.implode(',', Attachment::ALLOWED_EXTENSIONS)],
            'responsible' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'files.required' => 'Attach at least one file or photo proving the corrective actions were carried out.',
            'files.*.extensions' => 'Use photos, videos (mp4, mov), PDF or Office files.',
        ]);

        DB::transaction(function () use ($workflow, $round): void {
            $round->update(['evidence_responsible' => $this->responsible, 'evidence_notes' => $this->notes ?: null]);

            foreach ($this->files as $file) {
                Attachment::store($round, $file, Attachment::IMPLEMENTATION_EVIDENCE, auth()->user());
            }

            $workflow->apply($this->car, auth()->user(), CarAction::UploadEvidence);
        });

        session()->flash('status', "Implementation evidence uploaded. {$this->car->ownerLabel()} checks whether it was effective.");
        $this->redirectRoute('cars.show', $this->car, navigate: true);
    }

    public function render(): View
    {
        $round = $this->car->currentRound()->firstOrFail();

        return view('livewire.cars.evidence-form', [
            'round' => $round,
            'existingFiles' => $round->attachments()->where('collection', Attachment::IMPLEMENTATION_EVIDENCE)->get(),
            'deadline' => $this->car->implementationDeadline(),
        ]);
    }
}
