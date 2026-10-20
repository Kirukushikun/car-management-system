<?php

namespace App\Services;

use App\Models\Category;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

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
        return $this->forCategories([$category], $issuedOn);
    }

    /**
     * A CAR with several categories gets the strictest timeline: the shortest response days and
     * the shortest implementation days among them.
     *
     * @param  iterable<Category>  $categories
     * @return array{response_days: int, implementation_days: int, response_due_on: CarbonImmutable, implementation_due_on: CarbonImmutable}
     */
    public function forCategories(iterable $categories, CarbonInterface $issuedOn): array
    {
        $categories = collect($categories);

        if ($categories->isEmpty()) {
            throw new InvalidArgumentException('Deadlines need at least one category.');
        }

        $issuedOn = CarbonImmutable::instance($issuedOn)->startOfDay();
        $responseDays = (int) $categories->min('response_days');
        $implementationDays = (int) $categories->min('implementation_days');

        return [
            'response_days' => $responseDays,
            'implementation_days' => $implementationDays,
            'response_due_on' => $issuedOn->addDays($responseDays),
            'implementation_due_on' => $issuedOn->addDays($implementationDays),
        ];
    }
}
