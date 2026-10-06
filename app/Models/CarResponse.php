<?php

namespace App\Models;

use Database\Factories\CarResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The Responder's Phase II answer for one round: interim containment (Step 7), root cause
 * (Step 8) and corrective actions (Step 9). Saved as a draft until submitted for approval.
 */
#[Fillable([
    'car_id', 'car_round_id', 'containment_actions', 'containment_starts_on', 'containment_ends_on',
    'containment_responsible', 'root_cause', 'root_cause_responsible', 'prepared_by', 'submitted_at',
])]
class CarResponse extends Model
{
    /** @use HasFactory<CarResponseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'containment_starts_on' => 'immutable_date',
            'containment_ends_on' => 'immutable_date',
            'submitted_at' => 'immutable_datetime',
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
     * @return BelongsTo<CarRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(CarRound::class, 'car_round_id');
    }

    /**
     * @return HasMany<CorrectiveAction, $this>
     */
    public function correctiveActions(): HasMany
    {
        return $this->hasMany(CorrectiveAction::class)->orderBy('position');
    }

    /**
     * Root-cause files and corrective-action documents, told apart by collection.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /**
     * Everything Steps 7–9 require is filled in: containment with dates and a responsible person,
     * a root cause typed or attached, and at least one complete corrective action.
     */
    public function isComplete(): bool
    {
        $filled = fn (?string $value): bool => filled($value);

        $containment = $filled($this->containment_actions) && $filled($this->containment_responsible)
            && $this->containment_starts_on && $this->containment_ends_on
            && $this->containment_ends_on->gte($this->containment_starts_on);

        $rootCause = ($filled($this->root_cause) || $this->attachments()->where('collection', Attachment::ROOT_CAUSE)->exists())
            && $filled($this->root_cause_responsible);

        $actions = $this->correctiveActions()->get();

        return $containment && $rootCause && $actions->isNotEmpty()
            && $actions->every(fn (CorrectiveAction $action): bool => $action->isComplete());
    }
}
