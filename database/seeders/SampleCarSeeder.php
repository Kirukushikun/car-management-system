<?php

namespace Database\Seeders;

use App\Enums\CarAction;
use App\Enums\ComplaintType;
use App\Enums\Role;
use App\Models\Attachment;
use App\Models\Car;
use App\Models\CarResponse;
use App\Models\Category;
use App\Models\Farm;
use App\Models\IssuedToUnit;
use App\Models\Subcategory;
use App\Models\User;
use App\Services\CarWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo data: the mockup's ten sample CARs (CAR-2026-0138 … 0147), replayed through CarWorkflow
 * on their original dates so every status, deadline, round and history entry is produced by the
 * real state machine. Optional — run it after TestSeeder with `db:seed --class=SampleCarSeeder`
 * for a populated demo. Refuses to run in production.
 */
class SampleCarSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * [farm, unit, line, category, sub-category, issued, complainant, problem, steps].
     * Each step is [action, date] or [action, date, new end date].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string, 8: list<array{0: CarAction, 1: string, 2?: string}>}>
     */
    private const SAMPLES = [
        ['PFC', 'PFC Production', 'TABLE EGG', 'Production Related', 'Shell Quality Defects', '2026-08-28', 'QA Sampling Team',
            'Thin and cracked shells found in routine QA sampling of the morning collection.', [
                [CarAction::Release, '2026-08-28'], [CarAction::SubmitResponse, '2026-08-29'], [CarAction::ApproveResponse, '2026-08-30'],
                [CarAction::UploadEvidence, '2026-09-03'], [CarAction::MarkEffective, '2026-09-04'], [CarAction::Accept, '2026-09-05'],
            ]],
        ['PFC', 'Table Egg Logistics', 'TABLE EGG', 'Logistics & Distribution Transport', 'Issuance & Delivery Errors', '2026-09-02', 'SM Hypermarket Distribution Desk',
            'Delivery arrived a day late with mixed customer orders on one pallet.', [
                [CarAction::Release, '2026-09-02'], [CarAction::SubmitResponse, '2026-09-02'], [CarAction::ApproveResponse, '2026-09-03'],
                [CarAction::UploadEvidence, '2026-09-05'], [CarAction::MarkEffective, '2026-09-06'], [CarAction::NotAccept, '2026-09-07', '2026-09-20'],
            ]],
        ['BROOKDALE', 'Eggroom', 'TABLE EGG', 'Compliance & Standards', 'Sanitation and Maintenance', '2026-09-05', 'Internal Audit',
            'Egg room sanitation checklist not completed for three consecutive days.', [
                [CarAction::Release, '2026-09-05'], [CarAction::SubmitResponse, '2026-09-06'],
            ]],
        ['HATCHERY', 'Hatchery', 'DOP', 'Production Related', 'Hatchery Production & Hatch Rates', '2026-09-08', 'Hatchery Shift Supervisor',
            'Hatch rate for setter 4 fell below target for two consecutive batches.', [
                [CarAction::Release, '2026-09-08'], [CarAction::SubmitResponse, '2026-09-09'], [CarAction::ApproveResponse, '2026-09-10'],
            ]],
        ['PFC', 'Egg Grading', 'TABLE EGG', 'Compliance & Standards', 'Size / Weight & Grading Discrepancy', '2026-09-11', 'Retail Partner Complaint Desk',
            'Medium-labelled trays contained small eggs.', [
                [CarAction::Release, '2026-09-11'],
            ]],
        ['RH/BBGC', 'DOP Logistics', 'DOP', 'Preparation & Distribution Transport', 'Issuance & Delivery Errors', '2026-09-13', 'Grower Farm Partner',
            'Chick boxes delivered short by 200 heads against the delivery receipt.', [
                [CarAction::Release, '2026-09-13'], [CarAction::SubmitResponse, '2026-09-13'], [CarAction::ApproveResponse, '2026-09-14'],
                [CarAction::UploadEvidence, '2026-09-16'],
            ]],
        ['PFC', 'PFC Sales', 'TABLE EGG', 'Logistics & Distribution Transport', 'Issuance & Delivery Errors', '2026-09-14', 'Distributor Account',
            'Wrong quantity issued against the distributor order.', [
                [CarAction::Release, '2026-09-14'],
            ]],
        ['HATCHERY', 'DOP Logistics', 'DOP', 'Sales & Order Management', 'Booking & Sales Cancellations', '2026-09-16', 'Grower Booking Desk',
            'Confirmed chick booking cancelled the day before pull-out.', [
                [CarAction::Release, '2026-09-16'], [CarAction::SubmitResponse, '2026-09-16'], [CarAction::ApproveResponse, '2026-09-16'],
                [CarAction::UploadEvidence, '2026-09-16'], [CarAction::MarkEffective, '2026-09-17'],
            ]],
        ['BROOKDALE', 'Eggroom', 'TABLE EGG', 'Compliance & Standards', 'Storage & Handling', '2026-09-17', 'Internal Audit',
            'Transfer records did not match the egg room inventory count.', [
                [CarAction::Release, '2026-09-17'],
            ]],
        ['PFC', 'PFC Production', 'TABLE EGG', 'Production Related', 'Internal Quality Defects', '2026-09-17', 'Rollie Funa (customer)',
            'Saluyot customer Rollie Funa reported that 34 trays and 7 pcs of big dirty eggs from his August 20, 2026 purchase were returned by his customer as spoiled (foul smell). The eggs were disposed of; the customer has a video and is asking for a replacement.', []],
    ];

    /**
     * Phase II answers for the samples that reach "Submit response", keyed by complainant:
     * [containment, root cause, [corrective action, ...]].
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>}>
     */
    private const RESPONSES = [
        'QA Sampling Team' => [
            'Held the morning collection from dispatch and re-candled all trays from the affected houses.',
            'Calcium supplement in the layer feed ran short for four days after a delayed feed delivery.',
            ['Add a minimum-stock alert for the calcium premix.', 'Re-check shell strength daily for two weeks.'],
        ],
        'SM Hypermarket Distribution Desk' => [
            'Called the customer, re-sorted the mixed pallet and delivered the missing cases the same afternoon.',
            'Loading list was printed before the final order change and the loader worked from the old list.',
            ['Reprint and countersign the loading list after the 3 PM order cut-off.'],
        ],
        'Internal Audit' => [
            'Completed the missed sanitation round and swabbed the egg room surfaces.',
            'The night-shift sanitation checklist had no owner after a staff transfer.',
            ['Name a checklist owner per shift and add the checklist to the shift handover.'],
        ],
        'Hatchery Shift Supervisor' => [
            'Moved the remaining eggs from setter 4 to setters 2 and 5.',
            'Setter 4 humidity sensor drifted out of calibration.',
            ['Replace the humidity sensor and add monthly calibration to PMS.'],
        ],
        'Grower Farm Partner' => [
            'Delivered the 200 missing heads on the next trip at no charge.',
            'Box count was taken before the last trolley was loaded.',
            ['Count boxes at the truck door against the delivery receipt before sealing.'],
        ],
        'Grower Booking Desk' => [
            'Re-offered the cancelled chicks to two waiting growers.',
            'Bookings could be cancelled after confirmation without sales manager sign-off.',
            ['Require sales manager approval for cancellations within 72 hours of pull-out.'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SampleCarSeeder creates demo CARs and must never run in production.');
        }

        $workflow = app(CarWorkflow::class);
        $requestor = User::where('email', 'gab.maglalang@car.test')->firstOrFail();
        $requestorApprover = User::where('email', 'stephanie.flores@car.test')->firstOrFail();

        DB::table('car_sequences')->insertOrIgnore(['year' => 2026, 'last_number' => 137]);

        try {
            foreach (self::SAMPLES as [$farmName, $unitName, $line, $categoryName, $subcategoryName, $issuedOn, $complainant, $problem, $steps]) {
                $farm = Farm::where('name', $farmName)->firstOrFail();
                $category = Category::whereRelation('businessLine', 'name', $line)->where('name', $categoryName)->firstOrFail();

                Carbon::setTestNow(CarbonImmutable::parse($issuedOn)->setTime(8, 0));

                $car = $workflow->submit($requestor, [
                    'farm_id' => $farm->id,
                    'issued_to_unit_id' => IssuedToUnit::where('name', $unitName)->firstOrFail()->id,
                    'category_id' => $category->id,
                    'subcategory_id' => Subcategory::where('category_id', $category->id)->where('name', $subcategoryName)->firstOrFail()->id,
                    'issued_by' => "{$requestor->name} — Requestor",
                    'complainant' => $complainant,
                    'complaint_type' => ComplaintType::Product,
                    'problem_details' => $problem,
                ]);

                foreach ($steps as $index => $step) {
                    Carbon::setTestNow(CarbonImmutable::parse($step[1])->setTime(9 + $index, 0));

                    $this->replay($workflow, $car, $step, $farm, $requestorApprover);
                }
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * @param  array{0: CarAction, 1: string, 2?: string}  $step
     */
    private function replay(CarWorkflow $workflow, Car $car, array $step, Farm $farm, User $requestorApprover): void
    {
        [$action] = $step;

        if ($action === CarAction::SubmitResponse) {
            $this->writeResponse($car);
        }

        if ($action === CarAction::UploadEvidence) {
            $this->writeEvidence($car);
        }

        $actor = match ($action) {
            CarAction::Release, CarAction::Accept, CarAction::NotAccept => $requestorApprover,
            CarAction::SubmitResponse, CarAction::UploadEvidence => $this->farmUser($farm, Role::Responder),
            default => $this->farmUser($farm, Role::ResponderApprover),
        };

        $workflow->apply($car, $actor, $action, newDueOn: isset($step[2]) ? CarbonImmutable::parse($step[2]) : null);
    }

    /**
     * Fill in the current round's Phase II response so the workflow accepts "Submit response".
     */
    private function writeResponse(Car $car): void
    {
        [$containment, $rootCause, $actions] = self::RESPONSES[$car->complainant];
        $today = CarbonImmutable::today();
        $responsible = $this->farmUser($car->farm, Role::Responder)->name;

        $response = CarResponse::create([
            'car_id' => $car->id,
            'car_round_id' => $car->currentRound()->firstOrFail()->id,
            'containment_actions' => $containment,
            'containment_starts_on' => $today,
            'containment_ends_on' => $today,
            'containment_responsible' => $responsible,
            'root_cause' => $rootCause,
            'root_cause_responsible' => $responsible,
        ]);

        foreach ($actions as $index => $description) {
            $response->correctiveActions()->create([
                'position' => $index + 1,
                'description' => $description,
                'responsible' => $responsible,
                'starts_on' => $today,
                'ends_on' => $car->implementation_due_on,
            ]);
        }
    }

    /**
     * Attach a one-page evidence PDF to the current round so the workflow accepts "Upload evidence".
     */
    private function writeEvidence(Car $car): void
    {
        $round = $car->currentRound()->firstOrFail();
        $responder = $this->farmUser($car->farm, Role::Responder);
        $path = "car_rounds/{$round->id}/".Str::uuid().'.pdf';

        Storage::disk('local')->put($path, $this->evidencePdf("Implementation evidence - {$car->reference}"));

        $round->update(['evidence_responsible' => $responder->name, 'evidence_notes' => 'Corrective actions carried out as planned; checklist attached.']);
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
     * A minimal valid one-page PDF showing a single line of text.
     */
    private function evidencePdf(string $text): string
    {
        $stream = "BT /F1 18 Tf 72 720 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    /**
     * The farm's Responder or Responder Approver, created as a demo account if the farm has none.
     */
    private function farmUser(Farm $farm, Role $role): User
    {
        $existing = User::where('farm_id', $farm->id)->where('role', $role)->first();

        if ($existing) {
            return $existing;
        }

        $handle = str($farm->name)->lower()->replace('/', '-').'.'.str($role->value)->replace('_', '-');

        return User::factory()->sample()->create([
            'name' => ucwords(strtolower($farm->name)).' '.$role->label(),
            'email' => "{$handle}@car.test",
            'role' => $role,
            'farm_id' => $farm->id,
        ]);
    }
}
