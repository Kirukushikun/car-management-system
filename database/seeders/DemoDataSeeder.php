<?php

namespace Database\Seeders;

use App\Enums\CarAction;
use App\Enums\ComplaintType;
use App\Enums\Role;
use App\Models\Attachment;
use App\Models\BusinessLine;
use App\Models\Car;
use App\Models\CarResponse;
use App\Models\Category;
use App\Models\Farm;
use App\Models\IssuedToUnit;
use App\Models\User;
use App\Services\CarWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Demo history for local and staging: about two years of CARs (from January last year to today),
 * each filed and moved through the real CarWorkflow on realistic dates — so the dashboard, the
 * period comparison and the CAR lists have something to show. Some CARs are rejected, returned,
 * not effective, not accepted, stalled (overdue) or voided along the way; recent ones are still
 * open. The same seed always produces the same data. No notifications are sent.
 *
 * Needs ReferenceDataSeeder and TestSeeder (the sample accounts). Refuses to run in production.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    private const COMPLAINANTS = [
        'SM Hypermarket Distribution Desk', 'Puregold Price Club', 'Robinsons Supermarket', 'Rollie Funa', 'Mang Inasal Commissary',
        'Jollibee Commissary', 'Waltermart Grocery', 'Savemore Market', 'Internal Audit', 'QA Laboratory', 'Lucena Outlet',
        'Batangas Distributor', 'Cavite Wet Market Dealer', 'Hotel Purchasing — Tagaytay', 'Bakeshop Chain Purchasing',
    ];

    /**
     * Words used to vary problem details and responses per category.
     *
     * @var array<string, array{problems: list<string>, containment: list<string>, causes: list<string>, actions: list<string>}>
     */
    private const TEXT = [
        'Production Related' => [
            'problems' => ['**%d trays** returned as spoiled from the morning collection.', 'Thin and cracked shells found in routine sampling — **%d pcs** affected.', 'Watery albumen reported by the customer on **%d trays**.', 'Hatch rate dropped below target for **%d** consecutive settings.'],
            'containment' => ['Held the remaining stock from the same batch and replaced the customer\'s order.', 'Segregated the affected flock\'s eggs and re-candled the batch.', 'Stopped dispatch from the affected house until checked.'],
            'causes' => ['Eggs were held **three days** at room temperature before dispatch.', 'Feed formulation changed without adjusting calcium levels.', 'Collection rounds were skipped during the night shift.', 'Incubator temperature drifted after a sensor fault.'],
            'actions' => ['Move eggs to cold storage on the day of collection.', 'Restore the calcium level in the layer feed and test weekly.', 'Add a second collection round and a sign-off sheet.', 'Calibrate incubator sensors every month.'],
        ],
        'Compliance & Standards' => [
            'problems' => ['Sanitation checklist not completed for **%d** days.', 'Storage temperature log missing for **%d** shifts.', 'Labels missing the production date on **%d** boxes.'],
            'containment' => ['Completed a full sanitation round and re-briefed the shift.', 'Re-labelled the affected boxes before release.', 'Checked all stored stock against the temperature limits.'],
            'causes' => ['No one was assigned to the checklist after the shift change.', 'The label printer template was not updated.', 'The logger battery ran out and nobody noticed.'],
            'actions' => ['Assign a named owner per shift for the checklist.', 'Lock the label template and add a pre-release check.', 'Add the logger to the weekly maintenance list.'],
        ],
        'default' => [
            'problems' => ['Delivery arrived late with mixed orders on **%d** pallets.', 'Wrong quantity delivered — short by **%d** trays.', 'Order booked twice and **%d** trays had to be returned.'],
            'containment' => ['Re-delivered the correct order the same day.', 'Called the customer and corrected the invoice.', 'Pulled the duplicate booking and confirmed the rest by phone.'],
            'causes' => ['Pick list was printed before the last order change.', 'Truck loading had no second check.', 'Bookings were entered in two systems.'],
            'actions' => ['Print pick lists only after the order cut-off.', 'Add a loading checklist signed by the driver.', 'Use one booking sheet for all orders.'],
        ],
    ];

    private Randomizer $random;

    private CarWorkflow $workflow;

    private CarbonImmutable $now;

    private User $requestorApprover;

    private User $admin;

    /**
     * @var Collection<int, User>
     */
    private Collection $requestors;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoDataSeeder creates demo CARs and must never run in production.');
        }

        Notification::fake();

        $this->random = new Randomizer(new Mt19937(2026));
        $this->workflow = app(CarWorkflow::class);
        $this->now = CarbonImmutable::now();
        $this->requestorApprover = User::where('role', Role::RequestorApprover)->where('is_active', true)->orderBy('id')->firstOrFail();
        $this->admin = User::where('role', Role::Admin)->where('is_active', true)->orderBy('id')->firstOrFail();
        $this->requestors = User::where('role', Role::Requestor)->where('is_active', true)->orderBy('id')->get();

        $plans = $this->plans();

        try {
            foreach ($plans as $plan) {
                $this->play($plan);
            }
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info("Filed {$plans->count()} demo CARs from {$plans->first()['issued']->format('M Y')} to {$plans->last()['issued']->format('M j, Y')}.");
    }

    /**
     * What to file and when: a few CARs every month, a little busier this year, sorted by issue date
     * so reference numbers run in order.
     *
     * @return Collection<int, array{issued: CarbonImmutable, farm: Farm, units: Collection<int, IssuedToUnit>, subcategories: Collection<int, mixed>, types: list<ComplaintType>, complainant: string, requestor: User, category: string}>
     */
    private function plans(): Collection
    {
        $lines = BusinessLine::with(['issuedToUnits', 'categories.subcategories'])->get()->keyBy('name');
        $farms = Farm::all()->keyBy('name');
        $plans = collect();

        for ($month = $this->now->subYear()->startOfYear(); $month->lte($this->now); $month = $month->addMonth()) {
            $isThisYear = $month->year === $this->now->year;
            $count = $isThisYear ? $this->random->getInt(7, 11) : $this->random->getInt(5, 9);
            $lastDay = $month->isSameMonth($this->now) ? max(1, $this->now->day - 1) : $month->daysInMonth;

            if ($month->isSameMonth($this->now)) {
                $count = (int) round($count * $lastDay / $month->daysInMonth);
            }

            for ($i = 0; $i < $count; $i++) {
                $farmName = $this->pick(['PFC' => 40, 'BROOKDALE' => 20, 'RH/BBGC' => 15, 'BFC' => 10, 'HATCHERY' => 15]);
                $line = $lines[$farmName === 'HATCHERY' ? 'DOP' : 'TABLE EGG'];
                $category = $this->pickCategory($line, $isThisYear);
                $subcategories = $this->pickSubcategories($category, $isThisYear);

                if ($this->chance(0.15) && $line->categories->count() > 1) {
                    $other = $line->categories->where('id', '!=', $category->id)->values()[$this->random->getInt(0, $line->categories->count() - 2)];
                    $subcategories = $subcategories->merge($other->subcategories->take(1));
                }

                $plans->push([
                    'issued' => $month->day($this->random->getInt(1, $lastDay))->setTime($this->random->getInt(7, 11), $this->random->getInt(0, 59)),
                    'farm' => $farms[$farmName],
                    'units' => collect($this->random->pickArrayKeys($line->issuedToUnits->values()->all(), $this->chance(0.2) ? 2 : 1))
                        ->map(fn (int $index): IssuedToUnit => $line->issuedToUnits->values()[$index]),
                    'subcategories' => $subcategories,
                    'types' => $this->chance(0.15) ? [ComplaintType::Product, ComplaintType::Service] : [$this->chance(0.8) ? ComplaintType::Product : ComplaintType::Service],
                    'complainant' => self::COMPLAINANTS[$this->random->getInt(0, count(self::COMPLAINANTS) - 1)],
                    'requestor' => $this->requestors[$this->random->getInt(0, $this->requestors->count() - 1)],
                    'category' => $category->name,
                ]);
            }
        }

        return $plans->sortBy(fn (array $plan): int => $plan['issued']->getTimestamp())->values();
    }

    /**
     * File one CAR and move it along its life, stopping at the first step that would be in the future.
     *
     * @param  array{issued: CarbonImmutable, farm: Farm, units: Collection<int, IssuedToUnit>, subcategories: Collection<int, mixed>, types: list<ComplaintType>, complainant: string, requestor: User, category: string}  $plan
     */
    private function play(array $plan): void
    {
        $at = $plan['issued'];
        Carbon::setTestNow($at);

        $details = [
            'farm_id' => $plan['farm']->id,
            'issued_to_unit_ids' => $plan['units']->pluck('id')->all(),
            'subcategory_ids' => $plan['subcategories']->pluck('id')->unique()->values()->all(),
            'complaint_types' => $plan['types'],
            'issued_by' => "{$plan['requestor']->name} — Complainant",
            'complainant' => $plan['complainant'],
            'problem_details' => sprintf($this->words($plan['category'], 'problems'), $this->random->getInt(3, 48)),
        ];

        $car = $this->workflow->submit($plan['requestor'], $details);
        $responder = User::where('farm_id', $plan['farm']->id)->where('role', Role::Responder)->orderBy('id')->firstOrFail();
        $responderApprover = User::where('farm_id', $plan['farm']->id)->where('role', Role::ResponderApprover)->orderBy('id')->firstOrFail();

        // Phase I — sometimes rejected once, then released (or voided as a duplicate).
        if ($this->chance(0.1)) {
            if (! $at = $this->step($car, $this->requestorApprover, CarAction::Reject, $at, 0, 1, 'Please add the batch number and the delivery date.')) {
                return;
            }
            if (! $at = $this->later($at, 1, 2)) {
                return;
            }
            $this->workflow->resubmit($car, $plan['requestor'], [...$details, 'problem_details' => $details['problem_details'].' Batch and delivery date added.']);
        }

        if ($this->chance(0.03)) {
            $this->step($car, $this->admin, CarAction::Void, $at, 1, 3, 'Duplicate of an earlier CAR.');

            return;
        }

        if (! $at = $this->step($car, $this->requestorApprover, CarAction::Release, $at, 0, 2)) {
            return;
        }

        // Phase II and III, once per solution, until it is accepted.
        for ($solution = 1; $solution <= 3; $solution++) {
            if ($this->chance(0.04)) {
                return; // stalled — the farm never answers, so it shows as overdue
            }

            $this->writeResponse($car, $plan['category'], $responder, $at);
            $late = $this->chance(0.25) ? $this->random->getInt(1, 4) : 0;

            if (! $at = $this->step($car, $responder, CarAction::SubmitResponse, $at, 0, $car->response_days + $late)) {
                return;
            }

            if ($this->chance(0.12)) {
                if (! $at = $this->step($car, $responderApprover, CarAction::ReturnResponse, $at, 1, 2, 'Root cause is too general — explain why it happened.')) {
                    return;
                }
                $this->writeResponse($car, $plan['category'], $responder, $at);
                if (! $at = $this->step($car, $responder, CarAction::SubmitResponse, $at, 1, 3)) {
                    return;
                }
            }

            if (! $at = $this->step($car, $responderApprover, CarAction::ApproveResponse, $at, 0, 2)) {
                return;
            }

            if ($this->chance(0.03)) {
                return; // stalled in implementation
            }

            $this->writeEvidence($car, $responder);

            if (! $at = $this->step($car, $responder, CarAction::UploadEvidence, $at, 3, max(4, $car->implementation_days + 3))) {
                return;
            }

            if ($solution < 3 && $this->chance(0.1)) {
                if (! $at = $this->step($car, $responderApprover, CarAction::MarkNotEffective, $at, 1, 3, 'Complaints continued after the fix.')) {
                    return;
                }

                continue;
            }

            if (! $at = $this->step($car, $responderApprover, CarAction::MarkEffective, $at, 1, 3)) {
                return;
            }

            if ($solution < 3 && $this->chance(0.07)) {
                $at = $this->later($at, 1, 3);

                if (! $at) {
                    return;
                }

                Carbon::setTestNow($at);
                $this->workflow->apply($car, $this->requestorApprover, CarAction::NotAccept, note: 'The customer still reports the problem.', newDueOn: $at->addDays(14));

                continue;
            }

            $remarks = $this->chance(0.3) ? 'Verified with the customer — **no repeat complaints**.' : null;
            $this->step($car, $this->requestorApprover, CarAction::Accept, $at, 1, 3, $remarks);

            return;
        }
    }

    /**
     * Apply an action a few days after $at; null (and nothing done) when that day is still to come.
     */
    private function step(Car $car, User $actor, CarAction $action, CarbonImmutable $at, int $minDays, int $maxDays, ?string $note = null): ?CarbonImmutable
    {
        $when = $this->later($at, $minDays, $maxDays);

        if ($when === null) {
            return null;
        }

        Carbon::setTestNow($when);
        $this->workflow->apply($car, $actor, $action, note: $note);

        return $when;
    }

    private function later(CarbonImmutable $at, int $minDays, int $maxDays): ?CarbonImmutable
    {
        $when = $at->addDays($this->random->getInt($minDays, max($minDays, $maxDays)))->setTime($this->random->getInt(8, 16), $this->random->getInt(0, 59));

        return $when->lte($this->now) ? $when : null;
    }

    private function writeResponse(Car $car, string $category, User $responder, CarbonImmutable $at): void
    {
        $round = $car->currentRound()->firstOrFail();
        $response = CarResponse::updateOrCreate(['car_round_id' => $round->id], [
            'car_id' => $car->id,
            'containment_actions' => $this->words($category, 'containment'),
            'containment_starts_on' => $at->toDateString(),
            'containment_ends_on' => $at->addDay()->toDateString(),
            'containment_responsible' => $responder->name,
            'root_cause' => $this->words($category, 'causes'),
            'root_cause_responsible' => $responder->name,
        ]);

        $response->correctiveActions()->delete();

        foreach (range(1, $this->random->getInt(1, 3)) as $position) {
            $response->correctiveActions()->create([
                'position' => $position,
                'description' => $this->words($category, 'actions'),
                'responsible' => $responder->name,
                'starts_on' => $at->addDay()->toDateString(),
                'ends_on' => $car->fresh()->implementationDeadline()->max($at->addDays(8))->toDateString(),
            ]);
        }
    }

    private function writeEvidence(Car $car, User $responder): void
    {
        $round = $car->currentRound()->firstOrFail();
        $path = "car_rounds/{$round->id}/".Str::uuid().'.pdf';

        Storage::disk('local')->put($path, SampleCarSeeder::evidencePdf("Implementation evidence - {$car->reference}"));

        $round->update(['evidence_responsible' => $responder->name, 'evidence_notes' => 'Corrective actions carried out as planned; **checklist** attached.']);
        $round->attachments()->create([
            'collection' => Attachment::IMPLEMENTATION_EVIDENCE,
            'disk' => 'local',
            'path' => $path,
            'original_name' => "{$car->reference}-evidence.pdf",
            'mime_type' => 'application/pdf',
            'size' => Storage::disk('local')->size($path),
            'uploaded_by' => $responder->id,
        ]);
    }

    /**
     * Production Related leads; this year a little more so, which the comparison will show.
     */
    private function pickCategory(BusinessLine $line, bool $isThisYear): Category
    {
        $weights = $line->categories->mapWithKeys(fn (Category $category): array => [
            $category->id => $category->name === 'Production Related' ? ($isThisYear ? 55 : 40) : 20,
        ])->all();

        return $line->categories->firstWhere('id', (int) $this->pick($weights));
    }

    /**
     * One sub-category, sometimes two. Shell quality defects rise this year — a visible "biggest rise".
     *
     * @return Collection<int, mixed>
     */
    private function pickSubcategories(Category $category, bool $isThisYear): Collection
    {
        $subcategories = $category->subcategories->values();
        $shell = $subcategories->firstWhere('name', 'Shell Quality Defects');
        $first = $isThisYear && $shell && $this->chance(0.35) ? $shell : $subcategories[$this->random->getInt(0, $subcategories->count() - 1)];

        $picked = collect([$first]);

        if ($subcategories->count() > 1 && $this->chance(0.25)) {
            $picked->push($subcategories->where('id', '!=', $first->id)->values()[$this->random->getInt(0, $subcategories->count() - 2)]);
        }

        return $picked;
    }

    private function words(string $category, string $kind): string
    {
        $options = (self::TEXT[$category] ?? self::TEXT['default'])[$kind];

        return $options[$this->random->getInt(0, count($options) - 1)];
    }

    private function chance(float $probability): bool
    {
        return $this->random->getInt(1, 1000) <= $probability * 1000;
    }

    /**
     * @param  array<int|string, int>  $weights
     */
    private function pick(array $weights): int|string
    {
        $roll = $this->random->getInt(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            if (($roll -= $weight) <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}
