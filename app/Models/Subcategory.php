<?php

namespace App\Models;

use App\Models\Concerns\CanBeDeactivated;
use App\Observers\AuditObserver;
use Database\Factories\SubcategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A CAR sub-category. The description is the matrix's "what to report" guidance.
 */
#[ObservedBy(AuditObserver::class)]
#[Fillable(['category_id', 'name', 'is_active', 'description'])]
class Subcategory extends Model
{
    /** @use HasFactory<SubcategoryFactory> */
    use CanBeDeactivated, HasFactory;

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Car, $this>
     */
    public function cars(): BelongsToMany
    {
        return $this->belongsToMany(Car::class, 'car_subcategory');
    }
}
