<?php

namespace App\Models;

use App\Enums\CarAction;
use App\Enums\CarStatus;
use Database\Factories\CarEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only CAR history entry: who did which action, from which status to which.
 */
#[Fillable(['car_id', 'round', 'action', 'from_status', 'to_status', 'actor_id', 'note', 'created_at'])]
class CarEvent extends Model
{
    /** @use HasFactory<CarEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('CAR history is append-only.'));
        static::deleting(fn () => throw new LogicException('CAR history is append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'action' => CarAction::class,
            'from_status' => CarStatus::class,
            'to_status' => CarStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
