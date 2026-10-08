<?php

namespace App\Livewire\Auth;

use App\Enums\Role;
use App\Exceptions\LoginFailed;
use App\Models\AccessLog;
use App\Models\User;
use App\Services\CentralAuthenticator;
use App\Services\LoginThrottle;
use App\Services\TurnstileVerifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Organization-standard sign-in (Authentication Implementation Guide):
 *
 *   1. Sample account? (anywhere but production, is_sample rows only) — local check, stop here.
 *   2. Cloudflare Turnstile (only when config('login.turnstile') is on).
 *   3. Lockout check — 3 failures lock the email for 15 minutes.
 *   4. Central Auth API → user id → local user, who must have been granted access.
 *
 * Every attempt is written to the access log. No accounts are self-registered.
 */
#[Layout('layouts::guest')]
#[Title('Sign in')]
class Login extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public string $turnstileToken = '';

    public function login(CentralAuthenticator $central, LoginThrottle $throttle, TurnstileVerifier $turnstile): void
    {
        $this->validate();

        if ($this->signInAsSample()) {
            return;
        }

        try {
            $turnstile->verify($this->turnstileToken, request()->ip());

            if ($throttle->isLocked($this->email)) {
                throw new LoginFailed('Account temporarily locked. Please try again in 15 minutes.', countsAsAttempt: false);
            }

            $result = $central->authenticate($this->email, $this->password);
        } catch (LoginFailed $failure) {
            $this->fail($failure, $throttle);
        }

        $throttle->clear($this->email);
        session([
            'auth_token' => $result['token'],
            'token_expires' => $result['expires_at'],
            'email' => $result['email'],
        ]);

        $this->completeSignIn($result['user']);
    }

    /**
     * Sample panel click: fill the form so signing in is one click plus Sign in.
     */
    public function useSample(string $email): void
    {
        abort_unless(config('login.sample_accounts'), 404);

        $this->email = User::where('is_sample', true)->where('email', $email)->firstOrFail()->email;
        $this->password = config('login.sample_password');
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $sampleAccounts = config('login.sample_accounts') ? $this->sampleAccounts() : collect();
        $roleCards = $sampleAccounts->groupBy(fn (User $user): string => $user->role->value)
            ->map(fn (Collection $accounts): User => $accounts->sortBy('id')->first())
            ->values();

        return view('livewire.auth.login', [
            'sampleAccounts' => $sampleAccounts,
            'roleCards' => $roleCards,
            'moreSampleAccounts' => $sampleAccounts->reject(fn (User $user): bool => $roleCards->contains('id', $user->id))->values(),
            'isNonProduction' => (bool) config('login.sample_accounts'),
            'turnstileSiteKey' => config('login.turnstile') ? config('services.turnstile.site_key') : null,
        ]);
    }

    /**
     * Sample-account path — never in production, only TestSeeder's accounts, checked locally.
     * No Turnstile, no lockout and no API call, so a dev machine without API credentials works.
     */
    private function signInAsSample(): bool
    {
        if (! config('login.sample_accounts')) {
            return false;
        }

        $sample = User::where('email', $this->email)->where('is_sample', true)->where('is_active', true)->first();

        if ($sample === null || ! Hash::check($this->password, $sample->password)) {
            return false;
        }

        $this->completeSignIn($sample);

        return true;
    }

    private function completeSignIn(User $user): void
    {
        Auth::login($user);
        session()->regenerate();

        $this->redirectIntended($user->role->homeUrl(), navigate: true);
    }

    /**
     * @throws ValidationException always — shows the failure under the right field
     */
    private function fail(LoginFailed $failure, LoginThrottle $throttle): never
    {
        $message = $failure->getMessage();

        if ($failure->countsAsAttempt) {
            $throttle->hit($this->email);
            $remaining = $throttle->remaining($this->email);
            $message .= $remaining > 0 ? " {$remaining} attempt(s) remaining." : ' The account is now locked for 15 minutes.';
        }

        if ($failure->field === 'email') {
            AccessLog::recordSignInFailure($this->email);
        }

        throw ValidationException::withMessages([$failure->field => $message]);
    }

    /**
     * @return Collection<int, User>
     */
    private function sampleAccounts(): Collection
    {
        $roleOrder = array_map(fn (Role $role): string => $role->value, Role::cases());

        return User::with('farm')
            ->where('is_sample', true)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (User $user): string => array_search($user->role->value, $roleOrder, true).'-'.$user->name)
            ->values();
    }
}
