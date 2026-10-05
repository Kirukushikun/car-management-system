<?php

namespace App\Models;

use App\Enums\CarAction;
use Database\Factories\CarRoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pass through Phase II/III. Round 1 opens on submit; "not effective" and "not accepted"
 * open the next one, so earlier responses and evidence are kept rather than overwritten.
 */
#[Fillable(['car_id', 'number', 'opened_by_action', 'due_on'])]
class CarRound extends Model
{
    /** @use HasFactory<CarRoundFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'opened_by_action' => CarAction::class,
            'due_on' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
