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
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use LogicException;

/**
 * A Corrective Action Request. Status changes go through CarWorkflow only.
 */
#[Fillable([
    'reference', 'status', 'current_round', 'requestor_id', 'issued_by', 'complainant', 'complaint_types',
    'problem_details', 'initial_action', 'initial_action_starts_on', 'initial_action_ends_on', 'initial_action_responsible',
    'farm_id', 'complaint_received_on',
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
        'reference', 'requestor_id', 'issued_by', 'complainant', 'complaint_types', 'problem_details',
        'initial_action', 'initial_action_starts_on', 'initial_action_ends_on', 'initial_action_responsible',
        'farm_id', 'complaint_received_on',
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
            'complaint_types' => AsEnumCollection::of(ComplaintType::class),
            'current_round' => 'integer',
            'response_days' => 'integer',
            'implementation_days' => 'integer',
            'complaint_received_on' => 'immutable_date',
            'initial_action_starts_on' => 'immutable_date',
            'initial_action_ends_on' => 'immutable_date',
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
     * The operational units the CAR is issued to ("Issued To" — one or more, all from one
     * business line, which the CAR's categories belong to as well).
     *
     * @return BelongsToMany<IssuedToUnit, $this>
     */
    public function issuedToUnits(): BelongsToMany
    {
        return $this->belongsToMany(IssuedToUnit::class, 'car_issued_to_unit')->orderBy('issued_to_units.id');
    }

    /**
     * "Eggroom, Egg Grading" — the units the CAR is issued to.
     */
    public function unitNames(): string
    {
        return $this->issuedToUnits->pluck('name')->join(', ');
    }

    /**
     * TABLE EGG or DOP — the line of the units (and of every category) on the CAR.
     */
    public function businessLineName(): ?string
    {
        $this->loadMissing('issuedToUnits.businessLine');

        return $this->issuedToUnits->first()?->businessLine->name;
    }

    /**
     * The sub-categories picked on the CAR. Each belongs to one category, so together they form
     * the CAR's category / sub-category pairs (see classification()).
     *
     * @return BelongsToMany<Subcategory, $this>
     */
    public function subcategories(): BelongsToMany
    {
        return $this->belongsToMany(Subcategory::class, 'car_subcategory')
            ->orderBy('subcategories.category_id')
            ->orderBy('subcategories.id');
    }

    /**
     * The category / sub-category pairs: each category once, with its picked sub-categories.
     *
     * @return Collection<int, array{category: Category, subcategories: Collection<int, Subcategory>}>
     */
    public function classification(): Collection
    {
        $this->loadMissing('subcategories.category');

        return $this->subcategories
            ->toBase()
            ->groupBy('category_id')
            ->map(fn (Collection $subcategories): array => ['category' => $subcategories->first()->category, 'subcategories' => $subcategories->values()])
            ->values();
    }

    /**
     * Short subject for headings and emails: the first sub-category, plus how many more.
     */
    public function subject(): string
    {
        $names = $this->subcategories->pluck('name');

        return $names->first().($names->count() > 1 ? ' +'.($names->count() - 1).' more' : '');
    }

    /**
     * "Product Nonconformity, Service Nonconformity".
     */
    public function complaintTypeLabels(): string
    {
        return ($this->complaint_types ?? collect())->map(fn (ComplaintType $type): string => $type->label())->join(', ');
    }

    /**
     * "Production Related, Compliance & Standards".
     */
    public function categoryNames(): string
    {
        return $this->classification()->map(fn (array $pair): string => $pair['category']->name)->join(', ');
    }

    /**
     * "Shell Quality Defects, Storage & Handling".
     */
    public function subcategoryNames(): string
    {
        return $this->subcategories->pluck('name')->join(', ');
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
     * Phase II answers, one per round.
     *
     * @return HasMany<CarResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(CarResponse::class)->orderBy('car_round_id');
    }

    /**
     * The response for the round in progress, if the Responder has started one.
     */
    public function currentResponse(): ?CarResponse
    {
        return $this->currentRound?->response;
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
     * and only their own CARs for a Requestor. A Responder Approver does not see responses they
     * may not review (Step 10): ones they prepared, or ones routed to another approver in the chain.
     *
     * @param  Builder<Car>  $query
     */
    public function scopeWaitingOn(Builder $query, User $user): void
    {
        $statuses = array_filter(CarStatus::cases(), fn (CarStatus $status): bool => $status->ownerRole() === $user->role);

        $query->whereIn('status', $statuses)
            ->when($user->role->isFarmScoped(), fn (Builder $query) => $query->where('farm_id', $user->farm_id))
            ->when($user->role === Role::Requestor, fn (Builder $query) => $query->where('requestor_id', $user->id))
            ->when($user->role === Role::ResponderApprover, fn (Builder $query) => $query->whereNot(fn (Builder $query) => $query
                ->where('status', CarStatus::AwaitingResponderApproval)
                ->whereExists(fn ($response) => $response->selectRaw('1')
                    ->from('car_responses')
                    ->join('car_rounds', 'car_rounds.id', '=', 'car_responses.car_round_id')
                    ->join('users as preparers', 'preparers.id', '=', 'car_responses.prepared_by')
                    ->whereColumn('car_rounds.car_id', 'cars.id')
                    ->whereColumn('car_rounds.number', 'cars.current_round')
                    ->where(fn ($reviewable) => $reviewable
                        ->where('preparers.id', $user->id)
                        ->orWhere(fn ($chained) => $chained
                            ->where('preparers.role', Role::ResponderApprover->value)
                            ->whereNotNull('preparers.approver_id')
                            ->where('preparers.approver_id', '<>', $user->id))))));
    }

    /**
     * Open CARs whose active deadline (see activeDueOn) is before today.
     *
     * @param  Builder<Car>  $query
     */
    public function scopeOverdue(Builder $query, ?CarbonInterface $today = null): void
    {
        $today = ($today ?? CarbonImmutable::today())->toDateString();
        $awaitingResponse = array_filter(CarStatus::open(), fn (CarStatus $status): bool => $status->awaitsResponse());
        $later = array_filter(CarStatus::open(), fn (CarStatus $status): bool => ! $status->awaitsResponse());

        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->whereIn('status', $awaitingResponse)->where('response_due_on', '<', $today))
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
     * The deadline that matters now: the response deadline until the farm first responds, then
     * the implementation deadline — or the revised one after a "not accepted". Null once finished.
     */
    public function activeDueOn(): ?CarbonImmutable
    {
        return match (true) {
            ! $this->status->isOpen() => null,
            $this->status->awaitsResponse() => $this->response_due_on,
            default => $this->revised_due_on ?? $this->implementation_due_on,
        };
    }

    /**
     * The date corrective actions must be finished by: the revised end date after a "not
     * accepted", otherwise the implementation deadline from the matrix.
     */
    public function implementationDeadline(): CarbonImmutable
    {
        return $this->revised_due_on ?? $this->implementation_due_on;
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
