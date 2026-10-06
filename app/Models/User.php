<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use App\Observers\AuditObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[ObservedBy(AuditObserver::class)]
#[Fillable(['name', 'email', 'password', 'role', 'farm_id', 'approver_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * The farm a Responder or Responder Approver belongs to; null for every other role.
     *
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * The person who approves this user's submissions (Step 10 approver chain).
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function initials(): string
    {
        return collect(explode(' ', str_replace('.', '', $this->name)))
            ->filter()
            ->map(fn (string $word): string => mb_substr($word, 0, 1))
            ->take(2)
            ->implode('');
    }

    /**
     * Where the user works, as shown under their name: the farm for Responder roles, otherwise
     * the side of the workflow they sit on.
     */
    public function workplaceLabel(): string
    {
        return match ($this->role) {
            Role::Requestor, Role::RequestorApprover => 'Issuing department',
            Role::Responder, Role::ResponderApprover => $this->farm?->name ?? 'No farm assigned',
            Role::Monitor => 'All farms',
            Role::Admin => 'IT',
        };
    }

    /**
     * "Responder · PFC" for farm-scoped roles, just the role label otherwise.
     */
    public function roleWithScope(): string
    {
        return $this->role->isFarmScoped() && $this->farm
            ? "{$this->role->label()} · {$this->farm->name}"
            : $this->role->label();
    }
}
