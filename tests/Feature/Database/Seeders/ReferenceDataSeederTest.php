<?php

use App\Models\BusinessLine;
use App\Models\Category;
use App\Models\Farm;
use App\Models\IssuedToUnit;
use App\Models\Subcategory;
use Database\Seeders\ReferenceDataSeeder;

it('seeds the farms, units and both matrices', function () {
    $this->seed(ReferenceDataSeeder::class);

    expect(Farm::orderBy('id')->pluck('name')->all())->toBe(['PFC', 'HATCHERY', 'BROOKDALE', 'RH/BBGC', 'BFC'])
        ->and(BusinessLine::orderBy('id')->pluck('name')->all())->toBe(['TABLE EGG', 'DOP'])
        ->and(IssuedToUnit::whereRelation('businessLine', 'name', 'DOP')->orderBy('id')->pluck('name')->all())->toBe(['Hatchery', 'DOP Logistics'])
        ->and(Category::count())->toBe(7)
        ->and(Subcategory::count())->toBe(18);
});

it('seeds the response and implementation days from the requirements', function (string $line, string $category, int $responseDays, int $implementationDays) {
    $this->seed(ReferenceDataSeeder::class);

    $seeded = Category::whereRelation('businessLine', 'name', $line)->where('name', $category)->sole();

    expect($seeded->response_days)->toBe($responseDays)
        ->and($seeded->implementation_days)->toBe($implementationDays);
})->with([
    ['TABLE EGG', 'Production Related', 3, 10],
    ['TABLE EGG', 'Compliance & Standards', 1, 5],
    ['TABLE EGG', 'Logistics & Distribution Transport', 1, 5],
    ['DOP', 'Production Related', 3, 10],
    ['DOP', 'Compliance & Standards', 1, 3],
    ['DOP', 'Preparation & Distribution Transport', 1, 5],
    ['DOP', 'Sales & Order Management', 1, 5],
]);

it('does not duplicate rows or overwrite edited days when run again', function () {
    $this->seed(ReferenceDataSeeder::class);
    $category = Category::whereRelation('businessLine', 'name', 'DOP')->where('name', 'Compliance & Standards')->sole();
    $category->update(['response_days' => 2, 'implementation_days' => 4]);

    $this->seed(ReferenceDataSeeder::class);

    expect(Category::count())->toBe(7)
        ->and(Subcategory::count())->toBe(18)
        ->and($category->fresh()->response_days)->toBe(2)
        ->and($category->fresh()->implementation_days)->toBe(4);
});
