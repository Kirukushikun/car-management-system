<?php

namespace App\Models;

use App\Models\Concerns\CanBeDeactivated;
use App\Observers\AuditObserver;
use Database\Factories\ComplaintTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * "Type of Complaint/Problem" from Part I of the paper CAR form (Product Nonconformity, …),
 * maintained by the IT Admin.
 */
#[ObservedBy(AuditObserver::class)]
#[Fillable(['name', 'is_active'])]
class ComplaintType extends Model
{
    /** @use HasFactory<ComplaintTypeFactory> */
    use CanBeDeactivated, HasFactory;

    /**
     * @return BelongsToMany<Car, $this>
     */
    public function cars(): BelongsToMany
    {
        return $this->belongsToMany(Car::class, 'car_complaint_type');
    }
}
