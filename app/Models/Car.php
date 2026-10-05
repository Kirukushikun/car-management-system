<?php

namespace App\Models;

use App\Enums\CarStatus;
use App\Enums\ComplaintType;
use App\Enums\Role;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\CarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

/**
 * A Corrective Action Request. Status changes go through CarWorkflow only.
 */
#[Fillable([
    'reference', 'status', 'current_round', 'requestor_id', 'issued_by', 'complainant', 'complaint_type',
    'problem_details', 'farm_id', 'issued_to_unit_id', 'category_id', 'subcategory_id', 'complaint_received_on',
    'issued_on', 'response_days', 'implementation_days', 'response_due_on', 'implementation_due_on',
    'revised_due_on', 'released_at', 'closed_at', 'voided_at',
])]
class Car extends Model
{
    /** @use HasFactory<CarFactory> */
    use HasFactory;

    /**
     * Phase I fields. They may change while the CAR is with the Requestor, never after release.
     *
     * @var list<string>
     */
    public const PHASE_ONE_FIELDS = [
        'reference', 'requestor_id', 'issued_by', 'complainant', 'complaint_type', 'problem_details',
        'farm_id', 'issued_to_unit_id', 'category_id', 'subcategory_id', 'complaint_received_on',
        'issued_on', 'response_days', 'implementation_days', 'response_due_on', 'implementation_due_on',
    ];

    protected static function booted(): void
    {
        static::updating(function (Car $car): void {
            if ($car->getOriginal('released_at') !== null && $car->isDirty(self::PHASE_ONE_FIELDS)) {
                throw new LogicException("Phase I details of {$car->reference} are locked after release.");
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CarStatus::class,
            'complaint_type' => ComplaintType::class,
            'current_round' => 'integer',
            'response_days' => 'integer',
            'implementation_days' => 'integer',
            'complaint_received_on' => 'immutable_date',
            'issued_on' => 'immutable_date',
            'response_due_on' => 'immutable_date',
            'implementation_due_on' => 'immutable_date',
            'revised_due_on' => 'immutable_date',
            'released_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requestor_id');
    }

    /**
     * The farm whose Responders must answer this CAR.
     *
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * @return BelongsTo<IssuedToUnit, $this>
     */
    public function issuedToUnit(): BelongsTo
    {
        return $this->belongsTo(IssuedToUnit::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Subcategory, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    /**
     * @return HasMany<CarRound, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(CarRound::class)->orderBy('number');
    }

    /**
     * @return HasOne<CarRound, $this>
     */
    public function currentRound(): HasOne
    {
        return $this->hasOne(CarRound::class)->latestOfMany('number');
    }

    /**
     * @return HasMany<CarEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(CarEvent::class)->orderBy('id');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('id');
    }

    /**
     * @param  Builder<Car>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', CarStatus::open());
    }

    /**
     * CARs waiting on this user: their role owns the status, on their farm for Responder roles,
     * and only their own CARs for a Requestor.
     *
     * @param  Builder<Car>  $query
     */
    public function scopeWaitingOn(Builder $query, User $user): void
    {
        $statuses = array_filter(CarStatus::cases(), fn (CarStatus $status): bool => $status->ownerRole() === $user->role);

        $query->whereIn('status', $statuses)
            ->when($user->role->isFarmScoped(), fn (Builder $query) => $query->where('farm_id', $user->farm_id))
            ->when($user->role === Role::Requestor, fn (Builder $query) => $query->where('requestor_id', $user->id));
    }

    /**
     * Open CARs whose active deadline (see activeDueOn) is before today.
     *
     * @param  Builder<Car>  $query
     */
    public function scopeOverdue(Builder $query, ?CarbonInterface $today = null): void
    {
        $today = ($today ?? CarbonImmutable::today())->toDateString();
        $phaseOne = array_filter(CarStatus::cases(), fn (CarStatus $status): bool => $status->phase() === 1);
        $later = array_filter(CarStatus::cases(), fn (CarStatus $status): bool => in_array($status->phase(), [2, 3], true));

        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->whereIn('status', $phaseOne)->where('response_due_on', '<', $today))
            ->orWhere(fn (Builder $query) => $query->whereIn('status', $later)->whereRaw('coalesce(revised_due_on, implementation_due_on) < ?', [$today])));
    }

    /**
     * The sidebar lists: "mine" (waiting on the user), "overdue", or everything.
     *
     * @param  Builder<Car>  $query
     */
    public function scopeForView(Builder $query, string $view, User $user): void
    {
        match ($view) {
            'mine' => $query->waitingOn($user),
            'overdue' => $query->overdue(),
            default => null,
        };
    }

    /**
     * The deadline that matters now: the response deadline in Phase I, then the implementation
     * deadline — or the revised one after a "not accepted". Null once the CAR is finished.
     */
    public function activeDueOn(): ?CarbonImmutable
    {
        return match ($this->status->phase()) {
            null => null,
            1 => $this->response_due_on,
            default => $this->revised_due_on ?? $this->implementation_due_on,
        };
    }

    public function isOverdue(?CarbonInterface $today = null): bool
    {
        $dueOn = $this->activeDueOn();

        return $dueOn !== null && $dueOn->lt(($today ?? CarbonImmutable::today())->startOfDay());
    }

    /**
     * "PFC Responder", "Requestor Approver", or null when nobody needs to act.
     */
    public function ownerLabel(): ?string
    {
        $role = $this->status->ownerRole();

        if ($role === null) {
            return null;
        }

        return $role->isFarmScoped() ? "{$this->farm->name} {$role->label()}" : $role->label();
    }
}
