<?php

namespace App\Services;

use App\Exceptions\LoginFailed;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Organization-standard sign-in against the central Auth API (bfcgroup.ph):
 *   1. POST /api/v1/auth/login            — checks the email and password, returns a token
 *   2. GET  /api/v1/users/get-user-id      — returns the person's central user id
 *   3. the local users row with that id    — only people granted access to this system get in
 *
 * Response shapes follow the Authentication Implementation Guide; confirm them against the real
 * API before go-live.
 */
class CentralAuthenticator
{
    /**
     * @return array{user: User, token: ?string, expires_at: ?string, email: string}
     *
     * @throws LoginFailed with a message safe to show on the login page
     */
    public function authenticate(string $email, string $password): array
    {
        try {
            $authResponse = $this->client()
                ->withToken((string) config('services.auth_api.api_key'))
                ->post($this->url('/api/v1/auth/login'), ['email' => $email, 'password' => $password]);

            if (! $authResponse->successful()) {
                throw new LoginFailed($authResponse->json('message') ?: 'Incorrect email or password.');
            }

            $email = (string) ($authResponse->json('email') ?? $email);

            $userResponse = $this->client()
                ->withHeaders(['x-api-key' => (string) config('services.auth_api.auth_user_api_key')])
                ->get($this->url('/api/v1/users/get-user-id'), ['email' => $email]);

            if (! $userResponse->successful()) {
                throw new LoginFailed('Failed to retrieve user information from the system.', countsAsAttempt: false);
            }
        } catch (ConnectionException $e) {
            Log::warning('Auth API unreachable', ['email' => $email, 'error' => $e->getMessage()]);

            throw new LoginFailed('Authentication service is currently unreachable. Please try again shortly.', countsAsAttempt: false);
        } catch (LoginFailed $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Auth login flow failed unexpectedly', ['email' => $email, 'error' => $e->getMessage()]);

            throw new LoginFailed('Authentication service error. Please try again.');
        }

        $user = User::find($userResponse->json('id'));

        if ($user === null) {
            throw new LoginFailed('You are not authorized to access this system.');
        }

        if (! $user->is_active) {
            throw new LoginFailed('Your access to this system has been deactivated. Contact the IT Admin.');
        }

        return [
            'user' => $user,
            'token' => $authResponse->json('token'),
            'expires_at' => $authResponse->json('expires_at'),
            'email' => $email,
        ];
    }

    /**
     * A hung API must never hang the login page; certificates are checked against storage/cacert.pem.
     */
    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withOptions(['verify' => storage_path('cacert.pem')])
            ->timeout(10)
            ->connectTimeout(5);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.auth_api.base_uri'), '/').$path;
    }
}
