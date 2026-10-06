<?php

use App\Models\Attachment;
use App\Models\CarResponse;

it('is complete with containment, a root cause and a complete corrective action', function () {
    expect(CarResponse::factory()->create()->isComplete())->toBeTrue();
});

it('is incomplete when a Step 7 or Step 8 field is missing', function (string $field) {
    expect(CarResponse::factory()->create([$field => null])->isComplete())->toBeFalse();
})->with(['containment_actions', 'containment_starts_on', 'containment_ends_on', 'containment_responsible', 'root_cause', 'root_cause_responsible']);

it('accepts an attached root-cause file instead of typed text', function () {
    $response = CarResponse::factory()->create(['root_cause' => null]);
    Attachment::factory()->create([
        'attachable_type' => 'car_response',
        'attachable_id' => $response->id,
        'collection' => Attachment::ROOT_CAUSE,
    ]);

    expect($response->isComplete())->toBeTrue();
});

it('is incomplete when containment ends before it starts', function () {
    $response = CarResponse::factory()->create(['containment_starts_on' => '2026-10-05', 'containment_ends_on' => '2026-10-04']);

    expect($response->isComplete())->toBeFalse();
});

it('is incomplete without a corrective action, or with an unfinished one', function () {
    $response = CarResponse::factory()->create();

    $response->correctiveActions()->update(['responsible' => null]);
    expect($response->isComplete())->toBeFalse();

    $response->correctiveActions()->delete();
    expect($response->isComplete())->toBeFalse();
});
