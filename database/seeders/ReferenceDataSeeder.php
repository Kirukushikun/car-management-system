<?php

namespace Database\Seeders;

use App\Models\BusinessLine;
use App\Models\Category;
use App\Models\Farm;
use App\Models\IssuedToUnit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Farms, business lines, Issued To units and the Table Egg / DOP CAR matrices, taken from
 * "CAR_DIGITALIZATION REQUEST REQUIREMENT 8.19.26.pdf".
 *
 * Safe to run in production and to re-run: rows are only created when missing, so day
 * values the IT Admin has edited are never overwritten.
 */
class ReferenceDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<string>
     */
    public const FARMS = ['PFC', 'HATCHERY', 'BROOKDALE', 'RH/BBGC', 'BFC'];

    /**
     * Issued To units per business line, from the requirements flowchart.
     *
     * @var array<string, list<string>>
     */
    public const UNITS = [
        'TABLE EGG' => ['Eggroom', 'Egg Grading', 'PFC Sales', 'PFC Production', 'Table Egg Logistics'],
        'DOP' => ['Hatchery', 'DOP Logistics'],
    ];

    /**
     * Category => [response days, implementation days, [sub-category => what to report]].
     *
     * @var array<string, array<string, array{0: int, 1: int, 2: array<string, string>}>>
     */
    public const MATRIX = [
        'TABLE EGG' => [
            'Production Related' => [3, 10, [
                'Shell Quality Defects' => 'Cracked, broken, thin shell, wrinkled/rough shell, dirty/stained shells, abnormal shape, discoloration.',
                'Internal Quality Defects' => 'Watery/weak albumen, low Haugh units, discolored yolk, blood spots, off-odor, mold, rotten eggs.',
                'Production / Flock-Related Quality' => 'Sudden quality drop linked to flock health, nutrition, stress, or disease — persistent quality decline.',
            ]],
            'Compliance & Standards' => [1, 5, [
                'Size / Weight & Grading Discrepancy' => 'Wrong size classification, grading issue, mixed sizes within packaging.',
                'Packaging & Labeling' => 'Damaged cartons, wrong labeling, incomplete batch/expiry info, improper packaging causing breakage.',
                'Storage & Handling' => 'Inventory movement, incorrect data given during transfer of table eggs, grades, labeling, batch/expiry dating, traceability, regulatory docs.',
                'Sanitation and Maintenance' => 'Logistics delivery and hauling vehicle hygiene, egg washing, egg grading and egg room sanitation and maintenance protocol, egg tray washing, egg calibration.',
            ]],
            'Logistics & Distribution Transport' => [1, 5, [
                'Issuance & Delivery Errors' => 'Cancellation of booked trips causing complaints of the logistics provider, wrong quantity, wrong delivery date, mixed customer orders, late loading of eggs delaying delivery, transit time, delivery timeliness, stacking/protection, breakages due to poor handling and negligence.',
            ]],
        ],
        'DOP' => [
            'Production Related' => [3, 10, [
                'Hatchery Production & Hatch Rates' => 'Low hatch %, poor chick yield, uneven hatching, delayed hatch, over/under production vs order.',
                'Internal Biosecurity & Flock Health' => 'Source breeder flock health concerns, isolation gaps, biosecurity breaches, disease monitoring issues.',
            ]],
            'Compliance & Standards' => [1, 3, [
                'Chick Quality & Uniformity' => 'Weak/dehydrated chicks, navel defects, uneven size/weight, splayed legs, deformities, poor vitality at hatch.',
                'Sexing & Strain Accuracy' => 'Sexing errors, mixed batches, labeling mismatch or errors.',
                'Vaccination & Health Protocols' => 'Incomplete/missed vaccinations, improper administration, missing records, wrong vaccine type, storage issues.',
                'Sanitation and Maintenance' => 'Logistics delivery vehicle hygiene, and PMS.',
                'Documentation & Certification Readiness' => 'Missing health certificates, delivery permits, hatchery delivery docs, traceability records.',
            ]],
            'Preparation & Distribution Transport' => [1, 5, [
                'Preparation & Shipment Readiness' => 'Delayed pull-out, improper holding/conditioning before dispatch, packaging issues, late loading and loading errors, transport arrangements.',
                'Issuance & Delivery Errors' => 'Wrong quantity, mixed customer orders, stacking/protection, unacceptable mortalities due to poor handling and negligence, non-compliance to the logistics checklist.',
            ]],
            'Sales & Order Management' => [1, 5, [
                'Booking & Sales Cancellations' => 'Unjustified/last-minute sales order cancellations, cancellation after confirmation causing hatchery/planning losses, volume discrepancies between bookings and actual take-up.',
            ]],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::FARMS as $farm) {
            Farm::firstOrCreate(['name' => $farm]);
        }

        foreach (self::MATRIX as $lineName => $categories) {
            $line = BusinessLine::firstOrCreate(['name' => $lineName]);

            foreach (self::UNITS[$lineName] as $unit) {
                IssuedToUnit::firstOrCreate(['name' => $unit], ['business_line_id' => $line->id]);
            }

            foreach ($categories as $categoryName => [$responseDays, $implementationDays, $subcategories]) {
                $category = Category::firstOrCreate(
                    ['business_line_id' => $line->id, 'name' => $categoryName],
                    ['response_days' => $responseDays, 'implementation_days' => $implementationDays],
                );

                foreach ($subcategories as $subcategoryName => $description) {
                    $category->subcategories()->firstOrCreate(['name' => $subcategoryName], ['description' => $description]);
                }
            }
        }
    }
}
