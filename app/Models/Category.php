<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A CAR category within a business line, carrying the deadline matrix: days allowed for the
 * response and for implementing the corrective action, both counted from the issued date.
 */
#[ObservedBy(AuditObserver::class)]
#[Fillable(['business_line_id', 'name', 'response_days', 'implementation_days'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_days' => 'integer',
            'implementation_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BusinessLine, $this>
     */
    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }

    /**
     * @return HasMany<Subcategory, $this>
     */
    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class);
    }
}
