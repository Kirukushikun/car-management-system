<?php

namespace App\Services;

/**
 * The last placeholder figures left from the UI scaffold: the dashboard's time averages,
 * monthly chart and repeat offenders, copied from the mockup. DashboardMetrics replaces them
 * in Phase 7, and this class is deleted then.
 */
class ScaffoldData
{
    /**
     * @return array{avg_response_days: float, avg_resolution_days: float, months: array<string, int>, repeat_offenders: list<array{category: string, subcategory: string, count: int}>}
     */
    public static function dashboard(): array
    {
        return [
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
}
