<?php

namespace App\Services;

use App\Exceptions\LoginFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile check on the login form. A no-op unless config('login.turnstile') is on
 * (keys filled in and APP_ENV is not local).
 */
class TurnstileVerifier
{
    /**
     * @throws LoginFailed when the challenge failed or could not be checked
     */
    public function verify(?string $token, ?string $ip): void
    {
        if (! config('login.turnstile')) {
            return;
        }

        try {
            $response = Http::asForm()->timeout(5)->connectTimeout(3)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('services.turnstile.secret'),
                    'response' => (string) $token,
                    'remoteip' => $ip,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Turnstile verification unreachable', ['error' => $e->getMessage()]);

            throw new LoginFailed('Human verification service is unreachable. Please try again shortly.', countsAsAttempt: false, field: 'turnstileToken');
        }

        if (! ($response->json('success') ?? false)) {
            throw new LoginFailed('Human verification failed. Please complete the challenge and try again.', countsAsAttempt: false, field: 'turnstileToken');
        }
    }
}
