<?php

namespace App\Models;

use Database\Factories\IssuedToUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The operational unit a CAR is issued to (Eggroom, Egg Grading, Hatchery, …).
 */
#[Fillable(['business_line_id', 'name'])]
class IssuedToUnit extends Model
{
    /** @use HasFactory<IssuedToUnitFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<BusinessLine, $this>
     */
    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }
}
