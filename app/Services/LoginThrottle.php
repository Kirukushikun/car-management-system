<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Organization-standard brute-force protection: 3 failed attempts lock the email for 15 minutes.
 * Cache keys: login_attempts_{sha1(email)} and login_lockout_{sha1(email)}.
 */
class LoginThrottle
{
    public function isLocked(string $email): bool
    {
        return Cache::has($this->lockoutKey($email));
    }

    public function attempts(string $email): int
    {
        return (int) Cache::get($this->attemptsKey($email), 0);
    }

    public function remaining(string $email): int
    {
        return max(0, config('login.max_attempts') - $this->attempts($email));
    }

    public function hit(string $email): void
    {
        $attempts = $this->attempts($email) + 1;

        Cache::put($this->attemptsKey($email), $attempts, now()->addSeconds(config('login.lockout_seconds')));

        if ($attempts >= config('login.max_attempts')) {
            Cache::put($this->lockoutKey($email), true, config('login.lockout_seconds'));
        }
    }

    public function clear(string $email): void
    {
        Cache::forget($this->attemptsKey($email));
        Cache::forget($this->lockoutKey($email));
    }

    private function attemptsKey(string $email): string
    {
        return 'login_attempts_'.sha1(strtolower($email));
    }

    private function lockoutKey(string $email): string
    {
        return 'login_lockout_'.sha1(strtolower($email));
    }
}
