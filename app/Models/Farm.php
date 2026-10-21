<?php

namespace App\Models;

use App\Models\Concerns\CanBeDeactivated;
use App\Observers\AuditObserver;
use Database\Factories\FarmFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A farm or site (PFC, HATCHERY, BROOKDALE, …). Responders and Responder Approvers belong to one.
 */
#[ObservedBy(AuditObserver::class)]
#[Fillable(['name', 'is_active'])]
class Farm extends Model
{
    /** @use HasFactory<FarmFactory> */
    use CanBeDeactivated, HasFactory;

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Car, $this>
     */
    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }
}
