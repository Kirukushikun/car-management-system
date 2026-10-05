<?php

namespace App\Models;

use Database\Factories\BusinessLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TABLE EGG or DOP. Decides which categories an Issued To unit can receive.
 */
#[Fillable(['name'])]
class BusinessLine extends Model
{
    /** @use HasFactory<BusinessLineFactory> */
    use HasFactory;

    /**
     * @return HasMany<IssuedToUnit, $this>
     */
    public function issuedToUnits(): HasMany
    {
        return $this->hasMany(IssuedToUnit::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
