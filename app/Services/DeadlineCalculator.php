<?php

namespace App\Services;

use App\Models\Category;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Response deadline = issued date + response days; implementation deadline = issued date +
 * implementation days (requirement, Step 4). Counted in calendar days — an assumption until
 * open decision #4 (calendar vs business days) is settled; a business-day variant would be a
 * drop-in replacement for this class.
 */
class DeadlineCalculator
{
    /**
     * @return array{response_days: int, implementation_days: int, response_due_on: CarbonImmutable, implementation_due_on: CarbonImmutable}
     */
    public function forCategory(Category $category, CarbonInterface $issuedOn): array
    {
        $issuedOn = CarbonImmutable::instance($issuedOn)->startOfDay();

        return [
            'response_days' => $category->response_days,
            'implementation_days' => $category->implementation_days,
            'response_due_on' => $issuedOn->addDays($category->response_days),
            'implementation_due_on' => $issuedOn->addDays($category->implementation_days),
        ];
    }
}
