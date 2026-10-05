<?php

namespace Database\Seeders;

use App\Enums\CarAction;
use App\Enums\ComplaintType;
use App\Enums\Role;
use App\Models\Car;
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

/**
 * Demo data: the mockup's ten sample CARs (CAR-2026-0138 … 0147), replayed through CarWorkflow
 * on their original dates so every status, deadline, round and history entry is produced by the
 * real state machine. Local development only. Requires ReferenceDataSeeder and UserSeeder.
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
     * Run the database seeds.
     */
    public function run(): void
    {
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

        $actor = match ($action) {
            CarAction::Release, CarAction::Accept, CarAction::NotAccept => $requestorApprover,
            CarAction::SubmitResponse, CarAction::UploadEvidence => $this->farmUser($farm, Role::Responder),
            default => $this->farmUser($farm, Role::ResponderApprover),
        };

        $workflow->apply($car, $actor, $action, newDueOn: isset($step[2]) ? CarbonImmutable::parse($step[2]) : null);
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

        return User::factory()->create([
            'name' => ucwords(strtolower($farm->name)).' '.$role->label(),
            'email' => "{$handle}@car.test",
            'role' => $role,
            'farm_id' => $farm->id,
        ]);
    }
}
