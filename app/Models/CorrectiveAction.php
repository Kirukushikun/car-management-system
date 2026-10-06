<?php

namespace App\Models;

use Database\Factories\CorrectiveActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One corrective-action line (Step 9): what will be done, by whom, from when until when.
 */
#[Fillable(['car_response_id', 'position', 'description', 'responsible', 'starts_on', 'ends_on'])]
class CorrectiveAction extends Model
{
    /** @use HasFactory<CorrectiveActionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<CarResponse, $this>
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(CarResponse::class, 'car_response_id');
    }

    public function isComplete(): bool
    {
        return filled($this->description) && filled($this->responsible)
            && $this->starts_on && $this->ends_on && $this->ends_on->gte($this->starts_on);
    }
}
