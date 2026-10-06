<?php

namespace App\Http\Controllers;

use App\Enums\CarAction;
use App\Models\AccessLog;
use App\Models\Attachment;
use App\Models\Car;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class CarPrintController extends Controller
{
    /**
     * A print-ready CAR in the layout of the paper "Corrective Action Report Form" (Parts I–VI),
     * for filing or wet signatures. Anyone who may view the CAR may print it.
     */
    public function __invoke(Car $car): View
    {
        Gate::authorize('view', $car);
        AccessLog::recordAccess('print', $car->reference);

        $car->load([
            'farm', 'issuedToUnit.businessLine', 'category', 'subcategory', 'requestor', 'attachments',
            'events.actor',
            'responses' => fn ($query) => $query->whereNotNull('submitted_at')->with(['round', 'correctiveActions', 'preparer', 'attachments']),
            'rounds' => fn ($query) => $query->with(['evidenceUploader', 'attachments' => fn ($query) => $query->where('collection', Attachment::IMPLEMENTATION_EVIDENCE)]),
        ]);

        $events = $car->events->groupBy('round');

        return view('cars.print', [
            'car' => $car,
            'response' => $car->responses->sortByDesc(fn ($response) => $response->round->number)->first(),
            'approval' => $car->events->last(fn ($event) => $event->action === CarAction::ApproveResponse),
            'release' => $car->events->last(fn ($event) => $event->action === CarAction::Release),
            'verifications' => $car->rounds
                ->filter(fn ($round) => $round->evidence_uploaded_at !== null)
                ->map(fn ($round): array => [
                    'round' => $round,
                    'check' => ($events[$round->number] ?? collect())->last(fn ($event) => in_array($event->action, [CarAction::MarkEffective, CarAction::MarkNotEffective], true)),
                    'acceptance' => ($events[$round->number] ?? collect())->last(fn ($event) => in_array($event->action, [CarAction::Accept, CarAction::NotAccept], true)),
                    'recommitOn' => $car->rounds->firstWhere('number', $round->number + 1)?->due_on,
                ])
                ->values(),
        ]);
    }
}
