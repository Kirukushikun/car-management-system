<?php

namespace App\Models;

use Database\Factories\FarmFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A farm or site (PFC, HATCHERY, BROOKDALE, …). Responders and Responder Approvers belong to one.
 */
#[Fillable(['name'])]
class Farm extends Model
{
    /** @use HasFactory<FarmFactory> */
    use HasFactory;

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
