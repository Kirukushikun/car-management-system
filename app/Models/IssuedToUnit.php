<?php

namespace App\Models;

use App\Models\Concerns\CanBeDeactivated;
use App\Observers\AuditObserver;
use Database\Factories\IssuedToUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The operational unit a CAR is issued to (Eggroom, Egg Grading, Hatchery, …).
 */
#[ObservedBy(AuditObserver::class)]
#[Fillable(['business_line_id', 'name', 'is_active'])]
class IssuedToUnit extends Model
{
    /** @use HasFactory<IssuedToUnitFactory> */
    use CanBeDeactivated, HasFactory;

    /**
     * @return BelongsTo<BusinessLine, $this>
     */
    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }

    /**
     * @return BelongsToMany<Car, $this>
     */
    public function cars(): BelongsToMany
    {
        return $this->belongsToMany(Car::class, 'car_issued_to_unit');
    }
}
