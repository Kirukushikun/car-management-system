<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Hardcoded sample data for the UI scaffold (development plan, Phase 0).
 *
 * This is a straight port of `essentials/car-management-system-mockup.html`. It contains
 * no persistence and no real workflow — every action button is a stub. Each later phase
 * replaces one part of it with real models, and the class is deleted once nothing uses it:
 * farms/units/matrix moved to the database in Phase 1; cars/owners/deadlines → Phase 2, actions → Phases 3–5,
 * dashboard figures → Phase 7.
 */
class ScaffoldData
{
    /**
     * The mockup's frozen "today", so sample deadlines and overdue flags look the same as the mockup.
     */
    public const TODAY = '2026-09-17';

    /**
     * Business line of each unit used by the sample CARs. The real list lives in issued_to_units.
     *
     * @return array<string, string>
     */
    private static function sampleUnitLines(): array
    {
        return [
            'Eggroom' => 'TABLE EGG',
            'Egg Grading' => 'TABLE EGG',
            'PFC Sales' => 'TABLE EGG',
            'PFC Production' => 'TABLE EGG',
            'Table Egg Logistics' => 'TABLE EGG',
            'Hatchery' => 'DOP',
            'DOP Logistics' => 'DOP',
        ];
    }

    /**
     * Frozen copy of the matrix for the sample CARs' deadlines (like the snapshotted deadlines real
     * CARs will carry). The editable matrix lives in the categories table since Phase 1.
     *
     * @return array<string, array<string, array{response: int, implementation: int, subcategories: list<string>}>>
     */
    private static function sampleTimelines(): array
    {
        return [
            'TABLE EGG' => [
                'Production Related' => ['response' => 3, 'implementation' => 10, 'subcategories' => [
                    'Shell Quality Defects', 'Internal Quality Defects', 'Production / Flock-Related Quality',
                ]],
                'Compliance & Standards' => ['response' => 1, 'implementation' => 5, 'subcategories' => [
                    'Size / Weight & Grading Discrepancy', 'Packaging & Labeling', 'Storage & Handling', 'Sanitation and Maintenance',
                ]],
                'Logistics & Distribution Transport' => ['response' => 1, 'implementation' => 5, 'subcategories' => [
                    'Issuance & Delivery Errors',
                ]],
            ],
            'DOP' => [
                'Production Related' => ['response' => 3, 'implementation' => 10, 'subcategories' => [
                    'Hatchery Production & Hatch Rates', 'Internal Biosecurity & Flock Health',
                ]],
                'Compliance & Standards' => ['response' => 1, 'implementation' => 3, 'subcategories' => [
                    'Chick Quality & Uniformity', 'Sexing & Strain Accuracy', 'Vaccination & Health Protocols',
                    'Sanitation and Maintenance', 'Documentation & Certification Readiness',
                ]],
                'Preparation & Distribution Transport' => ['response' => 1, 'implementation' => 5, 'subcategories' => [
                    'Preparation & Shipment Readiness', 'Issuance & Delivery Errors',
                ]],
                'Sales & Order Management' => ['response' => 1, 'implementation' => 5, 'subcategories' => [
                    'Booking & Sales Cancellations',
                ]],
            ],
        ];
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'Awaiting Responder', 'Awaiting Implementation' => 'blue',
            'Awaiting Release', 'Awaiting Responder Approval', 'Awaiting Requestor Approval' => 'violet',
            'Awaiting Effectiveness Check' => 'amber',
            'Returned to Requestor', 'Returned to Responder', 'Open — Not Accepted' => 'red',
            'Closed — Accepted' => 'green',
            default => 'slate',
        };
    }

    /**
     * Sample CARs, newest last, each with its own history log.
     *
     * @return list<array{ref: string, farm: string, unit: string, line: string, category: string, subcategory: string, issued: string, complainant: string, status: string, new_end: ?string, history: list<array{date: string, who: string, what: string, tone: string}>}>
     */
    public static function cars(): array
    {
        $issued = 'CAR issued — ';
        $responded = 'Interim containment logged; root cause and corrective action submitted for approval.';
        $approved = 'Root cause & corrective action approved — routed to Phase III.';
        $evidence = 'Implementation evidence uploaded.';
        $effective = 'Corrective action validated as effective — forwarded to the Requestor Approver for final acceptance.';

        $rows = [
            ['CAR-2026-0138', 'PFC', 'PFC Production', 'Production Related', 'Shell Quality Defects', '2026-08-28', 'QA Sampling Team', 'Closed — Accepted', null, [
                ['2026-08-28', 'QA Sampling Team', $issued.'Shell Quality Defects', 'default'],
                ['2026-08-29', 'PFC Responder', $responded, 'default'],
                ['2026-08-30', 'PFC Responder Approver', $approved, 'green'],
                ['2026-09-03', 'PFC Responder', $evidence, 'default'],
                ['2026-09-04', 'PFC Responder Approver', $effective, 'green'],
                ['2026-09-05', 'Requestor Approver', 'Final acceptance approved — CAR closed.', 'green'],
            ]],
            ['CAR-2026-0139', 'PFC', 'Table Egg Logistics', 'Logistics & Distribution Transport', 'Issuance & Delivery Errors', '2026-09-02', 'SM Hypermarket Distribution Desk', 'Open — Not Accepted', '2026-09-20', [
                ['2026-09-02', 'SM Hypermarket Distribution Desk', $issued.'Issuance & Delivery Errors', 'default'],
                ['2026-09-02', 'PFC Responder', $responded, 'default'],
                ['2026-09-03', 'PFC Responder Approver', $approved, 'green'],
                ['2026-09-05', 'PFC Responder', $evidence, 'default'],
                ['2026-09-06', 'PFC Responder Approver', $effective, 'green'],
                ['2026-09-07', 'Requestor Approver', 'Not accepted — new end date Sep 20, 2026. Responder and Responder Approver flagged; looped back to Step 11.', 'red'],
            ]],
            ['CAR-2026-0140', 'BROOKDALE', 'Eggroom', 'Compliance & Standards', 'Sanitation and Maintenance', '2026-09-05', 'Internal Audit', 'Awaiting Responder Approval', null, [
                ['2026-09-05', 'Internal Audit', $issued.'Sanitation and Maintenance', 'default'],
                ['2026-09-06', 'BROOKDALE Responder', $responded, 'default'],
            ]],
            ['CAR-2026-0141', 'HATCHERY', 'Hatchery', 'Production Related', 'Hatchery Production & Hatch Rates', '2026-09-08', 'Hatchery Shift Supervisor', 'Awaiting Implementation', null, [
                ['2026-09-08', 'Hatchery Shift Supervisor', $issued.'Hatchery Production & Hatch Rates', 'default'],
                ['2026-09-09', 'HATCHERY Responder', $responded, 'default'],
                ['2026-09-10', 'HATCHERY Responder Approver', $approved, 'green'],
            ]],
            ['CAR-2026-0142', 'PFC', 'Egg Grading', 'Compliance & Standards', 'Size / Weight & Grading Discrepancy', '2026-09-11', 'Retail Partner Complaint Desk', 'Awaiting Responder', null, [
                ['2026-09-11', 'Retail Partner Complaint Desk', $issued.'Size / Weight & Grading Discrepancy', 'default'],
            ]],
            ['CAR-2026-0143', 'RH/BBGC', 'DOP Logistics', 'Preparation & Distribution Transport', 'Issuance & Delivery Errors', '2026-09-13', 'Grower Farm Partner', 'Awaiting Effectiveness Check', null, [
                ['2026-09-13', 'Grower Farm Partner', $issued.'Issuance & Delivery Errors', 'default'],
                ['2026-09-13', 'RH/BBGC Responder', $responded, 'default'],
                ['2026-09-14', 'RH/BBGC Responder Approver', $approved, 'green'],
                ['2026-09-16', 'RH/BBGC Responder', $evidence, 'default'],
            ]],
            ['CAR-2026-0144', 'PFC', 'PFC Sales', 'Logistics & Distribution Transport', 'Issuance & Delivery Errors', '2026-09-14', 'Distributor Account', 'Awaiting Responder', null, [
                ['2026-09-14', 'Distributor Account', $issued.'Issuance & Delivery Errors', 'default'],
            ]],
            ['CAR-2026-0145', 'HATCHERY', 'DOP Logistics', 'Sales & Order Management', 'Booking & Sales Cancellations', '2026-09-16', 'Grower Booking Desk', 'Awaiting Requestor Approval', null, [
                ['2026-09-16', 'Grower Booking Desk', $issued.'Booking & Sales Cancellations', 'default'],
                ['2026-09-16', 'HATCHERY Responder', $responded, 'default'],
                ['2026-09-16', 'HATCHERY Responder Approver', $approved, 'green'],
                ['2026-09-16', 'HATCHERY Responder', 'Implementation evidence uploaded (minor issue, fast turnaround).', 'default'],
                ['2026-09-17', 'HATCHERY Responder Approver', $effective, 'green'],
            ]],
            ['CAR-2026-0146', 'BROOKDALE', 'Eggroom', 'Compliance & Standards', 'Storage & Handling', '2026-09-17', 'Internal Audit', 'Awaiting Responder', null, [
                ['2026-09-17', 'Internal Audit', $issued.'Storage & Handling', 'default'],
            ]],
            ['CAR-2026-0147', 'PFC', 'PFC Production', 'Production Related', 'Internal Quality Defects', '2026-09-17', 'Rollie Funa (customer)', 'Awaiting Release', null, [
                ['2026-09-17', 'Gab Maglalang — Requestor', $issued.'Internal Quality Defects (submitted for release)', 'default'],
            ]],
        ];

        return array_map(fn (array $row): array => [
            'ref' => $row[0],
            'farm' => $row[1],
            'unit' => $row[2],
            'line' => self::sampleUnitLines()[$row[2]],
            'category' => $row[3],
            'subcategory' => $row[4],
            'issued' => $row[5],
            'complainant' => $row[6],
            'status' => $row[7],
            'new_end' => $row[8],
            'history' => array_map(fn (array $entry): array => [
                'date' => $entry[0], 'who' => $entry[1], 'what' => $entry[2], 'tone' => $entry[3],
            ], $row[9]),
        ], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $reference): ?array
    {
        return collect(self::cars())->firstWhere('ref', $reference);
    }

    public static function nextReference(): string
    {
        return 'CAR-2026-0148';
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::TODAY);
    }

    /**
     * Who must act on the CAR next — mirrors the mockup's ownerOf().
     *
     * @param  array<string, mixed>  $car
     * @return array{role: ?Role, label: string}
     */
    public static function owner(array $car): array
    {
        return match ($car['status']) {
            'Awaiting Release', 'Awaiting Requestor Approval' => ['role' => Role::RequestorApprover, 'label' => 'Requestor Approver'],
            'Returned to Requestor' => ['role' => Role::Requestor, 'label' => 'Requestor'],
            'Awaiting Responder', 'Returned to Responder', 'Awaiting Implementation', 'Open — Not Accepted' => ['role' => Role::Responder, 'label' => "{$car['farm']} Responder"],
            'Awaiting Responder Approval', 'Awaiting Effectiveness Check' => ['role' => Role::ResponderApprover, 'label' => "{$car['farm']} Resp. Approver"],
            default => ['role' => null, 'label' => 'No further action'],
        };
    }

    public static function phase(string $status): int
    {
        return match ($status) {
            'Awaiting Release', 'Returned to Requestor', 'Awaiting Responder' => 1,
            'Awaiting Responder Approval', 'Returned to Responder' => 2,
            default => 3,
        };
    }

    /**
     * @param  array<string, mixed>  $car
     * @return array{response: CarbonImmutable, implementation: CarbonImmutable, due: CarbonImmutable, response_days: int, implementation_days: int}
     */
    public static function deadlines(array $car): array
    {
        $timeline = self::sampleTimelines()[$car['line']][$car['category']];
        $issued = CarbonImmutable::parse($car['issued']);
        $implementation = $issued->addDays($timeline['implementation']);

        return [
            'response' => $issued->addDays($timeline['response']),
            'implementation' => $implementation,
            'due' => $car['new_end'] ? CarbonImmutable::parse($car['new_end']) : $implementation,
            'response_days' => $timeline['response'],
            'implementation_days' => $timeline['implementation'],
        ];
    }

    /**
     * The deadline that matters right now: response in Phase I, implementation (or revised) after.
     *
     * @param  array<string, mixed>  $car
     * @return array{date: CarbonImmutable, label: string}
     */
    public static function activeDeadline(array $car): array
    {
        $deadlines = self::deadlines($car);

        if (self::phase($car['status']) === 1) {
            return ['date' => $deadlines['response'], 'label' => 'Response deadline'];
        }

        return ['date' => $deadlines['due'], 'label' => $car['new_end'] ? 'Revised implementation deadline' : 'Implementation deadline'];
    }

    /**
     * @param  array<string, mixed>  $car
     */
    public static function isOverdue(array $car): bool
    {
        return ! str_starts_with($car['status'], 'Closed') && self::activeDeadline($car)['date']->lt(self::today());
    }

    /**
     * @param  array<string, mixed>  $car
     */
    public static function isOwnedBy(array $car, User $user): bool
    {
        if (self::owner($car)['role'] !== $user->role) {
            return false;
        }

        return ! $user->role->isFarmScoped() || $car['farm'] === $user->farm?->name;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function carsFor(string $view, User $user): array
    {
        $cars = collect(self::cars());

        $filtered = match ($view) {
            'mine' => $cars->filter(fn (array $car): bool => self::isOwnedBy($car, $user)),
            'overdue' => $cars->filter(fn (array $car): bool => self::isOverdue($car)),
            default => $cars,
        };

        return $filtered->values()->all();
    }

    /**
     * Stub action bar for the detail page: which buttons the signed-in role would see for this status.
     * Replaced by the CarWorkflow transition table in Phase 2.
     *
     * @param  array<string, mixed>  $car
     * @return array{note: string, buttons: list<array{label: string, primary: bool, phase: int}>, asks_new_end_date: bool}|null
     */
    public static function actionsFor(array $car, User $user): ?array
    {
        if (! self::isOwnedBy($car, $user)) {
            return null;
        }

        return match ($car['status']) {
            'Returned to Requestor' => self::bar('The Requestor Approver sent this back — it needs more investigation or clarity. Update the details, then resubmit for release.', [
                ['Resubmit for release', true, 3],
            ]),
            'Awaiting Release' => self::bar('Investigation done? Release this CAR to the Responder, or return it to the Requestor for clarity.', [
                ['Approve & release', true, 3], ['Reject CAR', false, 3],
            ]),
            'Awaiting Requestor Approval' => self::bar('The Responder Approver confirmed the corrective action was effective. Give final acceptance to close this CAR, or send it back with a new end date.', [
                ['Accept — close CAR', true, 5], ['Not accepted', false, 5],
            ], asksNewEndDate: true),
            'Awaiting Responder', 'Returned to Responder' => self::bar('Log the interim containment, root cause and corrective action, then send it to the Responder Approver.', [
                ['Submit response', true, 4],
            ]),
            'Awaiting Implementation' => self::bar('Upload proof that the corrective action was actually implemented.', [
                ['Upload implementation evidence', true, 5],
            ]),
            'Open — Not Accepted' => self::bar('The Requestor Approver did not accept this CAR. Re-implement and upload new evidence by the revised end date.', [
                ['Upload new evidence', true, 5],
            ]),
            'Awaiting Responder Approval' => self::bar('Review the root cause and corrective action before this moves to implementation.', [
                ['Approve', true, 4], ['Return for revision', false, 4],
            ]),
            'Awaiting Effectiveness Check' => self::bar('Was the corrective action effective?', [
                ['Mark effective', true, 5], ['Not effective', false, 5],
            ]),
            default => null,
        };
    }

    /**
     * Dashboard figures. Counts come from the sample CARs; time averages, the monthly series and
     * repeat offenders are the mockup's hardcoded values until Phase 7.
     *
     * @return array{open: int, phase_one: int, overdue: int, avg_response_days: float, avg_resolution_days: float, months: array<string, int>, repeat_offenders: list<array{category: string, subcategory: string, count: int}>}
     */
    public static function dashboard(): array
    {
        $cars = collect(self::cars());

        return [
            'open' => $cars->reject(fn (array $car): bool => str_starts_with($car['status'], 'Closed'))->count(),
            'phase_one' => $cars->filter(fn (array $car): bool => self::phase($car['status']) === 1)->count(),
            'overdue' => $cars->filter(fn (array $car): bool => self::isOverdue($car))->count(),
            'avg_response_days' => 1.8,
            'avg_resolution_days' => 7.2,
            'months' => ['Apr' => 9, 'May' => 11, 'Jun' => 7, 'Jul' => 14, 'Aug' => 10, 'Sep' => 8],
            'repeat_offenders' => [
                ['category' => 'Logistics & Distribution Transport', 'subcategory' => 'Issuance & Delivery Errors', 'count' => 6],
                ['category' => 'Production Related', 'subcategory' => 'Shell Quality Defects', 'count' => 4],
                ['category' => 'Compliance & Standards', 'subcategory' => 'Storage & Handling', 'count' => 3],
                ['category' => 'Compliance & Standards', 'subcategory' => 'Sanitation and Maintenance', 'count' => 3],
                ['category' => 'Production Related', 'subcategory' => 'Hatchery Production & Hatch Rates', 'count' => 2],
            ],
        ];
    }

    /**
     * @param  list<array{0: string, 1: bool, 2: int}>  $buttons
     * @return array{note: string, buttons: list<array{label: string, primary: bool, phase: int}>, asks_new_end_date: bool}
     */
    private static function bar(string $note, array $buttons, bool $asksNewEndDate = false): array
    {
        return [
            'note' => $note,
            'buttons' => array_map(fn (array $button): array => [
                'label' => $button[0], 'primary' => $button[1], 'phase' => $button[2],
            ], $buttons),
            'asks_new_end_date' => $asksNewEndDate,
        ];
    }
}
