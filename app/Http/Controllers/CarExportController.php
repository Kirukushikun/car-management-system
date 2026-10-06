<?php

namespace App\Http\Controllers;

use App\Enums\CarAction;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CarExportController extends Controller
{
    /**
     * Download the CAR list (same views as the screen: all, mine, overdue) as CSV for Excel.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Car::class);

        $view = (string) $request->query('view', '');
        match ($view) {
            'mine' => Gate::authorize('view-queue'),
            'overdue' => Gate::authorize('view-overdue'),
            default => $view = '',
        };

        $cars = Car::query()
            ->with(['farm', 'issuedToUnit.businessLine', 'category', 'subcategory', 'requestor', 'events' => fn ($query) => $query->where('action', CarAction::SubmitResponse)])
            ->forView($view, $request->user())
            ->orderBy('issued_on')
            ->orderBy('id')
            ->get();

        $filename = 'cars-'.($view ?: 'all').'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($cars): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Reference', 'Status', 'Owner', 'Overdue', 'Issued on', 'Complaint received', 'Farm', 'Issued to', 'Business line',
                'Category', 'Sub-category', 'Type of complaint', 'Complainant', 'Requestor', 'Response due', 'Implementation due',
                'Revised end date', 'Released', 'First response', 'Closed', 'Round',
            ]);

            foreach ($cars as $car) {
                fputcsv($out, [
                    $car->reference,
                    $car->status->label(),
                    $car->ownerLabel() ?? '',
                    $car->isOverdue() ? 'Yes' : 'No',
                    $car->issued_on->toDateString(),
                    $car->complaint_received_on?->toDateString() ?? '',
                    $car->farm->name,
                    $car->issuedToUnit->name,
                    $car->issuedToUnit->businessLine->name,
                    $car->category->name,
                    $car->subcategory->name,
                    $car->complaint_type->label(),
                    $car->complainant,
                    $car->requestor->name,
                    $car->response_due_on->toDateString(),
                    $car->implementation_due_on->toDateString(),
                    $car->revised_due_on?->toDateString() ?? '',
                    $car->released_at?->toDateString() ?? '',
                    $car->events->first()?->created_at->toDateString() ?? '',
                    $car->closed_at?->toDateString() ?? '',
                    $car->current_round,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
