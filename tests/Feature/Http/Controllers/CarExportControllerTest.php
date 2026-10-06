<?php

use App\Enums\CarStatus;
use App\Enums\Role;
use App\Models\Car;
use App\Models\User;

/**
 * @return list<list<string>>
 */
function exportedRows(string $csv): array
{
    $lines = array_filter(explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF"))));

    return array_values(array_map(fn (string $line): array => str_getcsv($line), $lines));
}

it('downloads every CAR as CSV with a header row', function () {
    $cars = Car::factory()->count(2)->create();

    $response = $this->actingAs(User::factory()->role(Role::Monitor)->create())
        ->get(route('cars.export'))
        ->assertOk()
        ->assertDownload('cars-all-'.now()->format('Y-m-d').'.csv');

    $rows = exportedRows($response->streamedContent());

    expect($rows[0][0])->toBe('Reference')
        ->and(array_column(array_slice($rows, 1), 0))->toEqualCanonicalizing($cars->pluck('reference')->all());
});

it('exports only the signed-in user\'s queue for view=mine', function () {
    $approver = User::factory()->role(Role::RequestorApprover)->create();
    $waiting = Car::factory()->status(CarStatus::AwaitingRelease)->create();
    Car::factory()->status(CarStatus::AwaitingResponder)->create();

    $rows = exportedRows($this->actingAs($approver)->get(route('cars.export', ['view' => 'mine']))->streamedContent());

    expect(array_column(array_slice($rows, 1), 0))->toBe([$waiting->reference]);
});

it('forbids views the role cannot open', function () {
    $this->actingAs(User::factory()->role(Role::Requestor)->create())
        ->get(route('cars.export', ['view' => 'overdue']))
        ->assertForbidden();
});

it('sends guests to sign in', function () {
    $this->get(route('cars.export'))->assertRedirect(route('login'));
});
