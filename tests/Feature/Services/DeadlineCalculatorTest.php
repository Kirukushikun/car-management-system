<?php

use App\Models\Category;
use App\Services\DeadlineCalculator;
use Carbon\CarbonImmutable;

it('adds the category response and implementation days to the issued date', function (int $responseDays, int $implementationDays, string $issuedOn, string $responseDue, string $implementationDue) {
    $category = new Category(['response_days' => $responseDays, 'implementation_days' => $implementationDays]);

    $deadlines = app(DeadlineCalculator::class)->forCategory($category, CarbonImmutable::parse($issuedOn));

    expect($deadlines['response_due_on']->toDateString())->toBe($responseDue)
        ->and($deadlines['implementation_due_on']->toDateString())->toBe($implementationDue)
        ->and($deadlines['response_days'])->toBe($responseDays)
        ->and($deadlines['implementation_days'])->toBe($implementationDays);
})->with([
    'production related' => [3, 10, '2026-09-04', '2026-09-07', '2026-09-14'],
    'DOP compliance' => [1, 3, '2026-09-08', '2026-09-09', '2026-09-11'],
    'across a month end' => [1, 5, '2026-09-28', '2026-09-29', '2026-10-03'],
    'counts weekends as calendar days' => [1, 5, '2026-10-03', '2026-10-04', '2026-10-08'],
]);

it('ignores the time of day the CAR was issued', function () {
    $category = new Category(['response_days' => 1, 'implementation_days' => 5]);

    $deadlines = app(DeadlineCalculator::class)->forCategory($category, CarbonImmutable::parse('2026-10-05 23:59:00'));

    expect($deadlines['response_due_on']->toDateTimeString())->toBe('2026-10-06 00:00:00');
});
