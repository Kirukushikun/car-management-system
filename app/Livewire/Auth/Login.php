<?php

namespace App\Livewire\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Email + password sign-in. There is no self-registration — the IT Admin creates accounts.
 * In the local environment it also lists one account per role so reviewers can switch roles,
 * replacing the mockup's "pick any role" screen.
 */
#[Layout('layouts::guest')]
#[Title('Sign in')]
class Login extends Component
{
    private const MAX_ATTEMPTS = 5;

    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        $credentials = ['email' => $this->email, 'password' => $this->password, 'is_active' => true];

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        $this->redirectIntended(Auth::user()->role->homeUrl(), navigate: true);
    }

    /**
     * Local-only shortcut: sign in as a seeded account without a password.
     */
    public function loginAs(int $userId): void
    {
        abort_unless(app()->isLocal(), 404);

        Auth::login(User::active()->findOrFail($userId));
        session()->regenerate();

        $this->redirect(Auth::user()->role->homeUrl(), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.login', [
            'demoUsers' => app()->isLocal() ? $this->demoUsers() : collect(),
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function demoUsers(): Collection
    {
        $roleOrder = array_map(fn (Role $role): string => $role->value, Role::cases());

        return User::active()
            ->get()
            ->unique(fn (User $user): string => $user->role->value)
            ->sortBy(fn (User $user): int => array_search($user->role->value, $roleOrder, true))
            ->values();
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}
