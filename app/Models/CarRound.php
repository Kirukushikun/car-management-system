<?php

namespace App\Models;

use App\Enums\CarAction;
use Database\Factories\CarRoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One pass through Phase II/III. Round 1 opens on submit; "not effective" and "not accepted"
 * open the next one, so earlier responses and evidence are kept rather than overwritten.
 */
#[Fillable(['car_id', 'number', 'opened_by_action', 'due_on', 'evidence_notes', 'evidence_responsible', 'evidence_uploaded_by', 'evidence_uploaded_at'])]
class CarRound extends Model
{
    /** @use HasFactory<CarRoundFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'opened_by_action' => CarAction::class,
            'due_on' => 'immutable_date',
            'evidence_uploaded_at' => 'immutable_datetime',
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
     * The Responder's Phase II answer for this round, if one has been started.
     *
     * @return HasOne<CarResponse, $this>
     */
    public function response(): HasOne
    {
        return $this->hasOne(CarResponse::class);
    }

    /**
     * Step 11 implementation evidence for this round.
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
    public function evidenceUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evidence_uploaded_by');
    }

    public function hasEvidence(): bool
    {
        return $this->attachments()->where('collection', Attachment::IMPLEMENTATION_EVIDENCE)->exists();
    }
}
